<?php
/**
 * Mail Helper — DST Recruitment
 *
 * Mengirim email via SMTP menggunakan PHPMailer.
 * Konfigurasi diambil dari .env (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, dll.)
 *
 * Terinspirasi dari konsep Kirim_email.php (tutorial YouTube) dan
 * wpu-login-master (CodeIgniter 3) oleh WPU — disesuaikan untuk CI4-style.
 */

// ─── 1. Load PHPMailer ───────────────────────────────────────────────────────
$_phpmailerBase = ROOTPATH . 'vendor/phpmailer/phpmailer/src/';
foreach (['PHPMailer.php', 'SMTP.php', 'Exception.php'] as $_f) {
    $fp = $_phpmailerBase . $_f;
    if (is_file($fp)) require_once $fp;
}

// ─── 2. Load Mail config (membaca .env lewat getenv) ─────────────────────────
if (is_file(APPPATH . 'Config/Mail.php')) {
    require_once APPPATH . 'Config/Mail.php';
}

/**
 * sendMail() — kirim email HTML via SMTP
 *
 * @param  string $to        Alamat email penerima
 * @param  string $subject   Subjek email
 * @param  string $htmlBody  Isi email (HTML)
 * @return array{success:bool, message:string}
 */
function sendMail(string $to, string $subject, string $htmlBody): array
{
    // ── Validasi kredensial ──────────────────────────────────────────────────
    $mailUser = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
    $mailPass = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
    $mailFrom = (defined('MAIL_FROM') && MAIL_FROM !== '') ? MAIL_FROM : $mailUser;

    if (empty($mailUser) || empty($mailPass)) {
        return _mailError('SMTP credentials not configured in .env');
    }
    if (in_array(strtolower($mailUser), ['your-email@gmail.com', 'change_me'], true) ||
        in_array($mailPass, ['your-app-password', 'change_me'], true)) {
        return _mailError('SMTP credentials masih berisi nilai placeholder');
    }

    // ── Cek PHPMailer tersedia ───────────────────────────────────────────────
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return _mailError('PHPMailer tidak ditemukan. Jalankan: composer require phpmailer/phpmailer');
    }

    // ── Konfigurasi SMTP (Dinamis Port 465 / 587) ────────────────────────────
    $mailPort   = defined('MAIL_PORT') ? (int) MAIL_PORT : 465;
    $rawHost    = defined('MAIL_HOST') ? MAIL_HOST : 'mail.karirdigdaya.web.id';
    $mailSecure = defined('MAIL_SECURE') ? strtolower(MAIL_SECURE) : '';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // ─ Server ─
        $mail->isSMTP();
        $mail->Host        = ltrim($rawHost, '/');
        $mail->SMTPAuth    = true;
        $mail->Username    = $mailUser;
        $mail->Password    = $mailPass;
        
        if ($mailSecure === 'ssl' || $mailPort === 465) {
            $mail->SMTPSecure  = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPAutoTLS = false;
            if (strpos($mail->Host, 'ssl://') !== 0) {
                $mail->Host = 'ssl://' . $mail->Host;
            }
        } else {
            $mail->SMTPSecure  = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
        }

        $mail->Port    = $mailPort;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = defined('MAIL_TIMEOUT') ? (int) MAIL_TIMEOUT : 30;

        // Bypass verifikasi SSL (diperlukan untuk shared hosting / self-signed cert)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        // ─ Pengirim ─
        $mail->setFrom($mailFrom, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'DST Recruitment');

        // ─ Penerima ─
        $mail->addAddress($to);

        // ─ Embed Logo (Mencegah spam block akibat URL localhost) ────────────────
        $logoPath = ROOTPATH . 'assets/images/logoDST.png';
        if (is_file($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo_dst');
        }

        // ─ Konten ─
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(html_entity_decode($htmlBody, ENT_QUOTES, 'UTF-8'));

        $mail->send();
        return ['success' => true, 'message' => 'Email berhasil dikirim'];

    } catch (\Throwable $e) {
        return _mailError($e->getMessage());
    }
}

/**
 * Helper internal: catat log error & kembalikan array gagal.
 */
function _mailError(string $msg): array
{
    $logDir  = ROOTPATH . 'storage/logs/';
    $logFile = $logDir . 'mail.log';

    // Buat folder storage/logs jika belum ada
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $entry = '[' . date('Y-m-d H:i:s') . '] MAIL ERROR: ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    error_log('DST Mail: ' . $msg);

    return ['success' => false, 'message' => $msg];
}
