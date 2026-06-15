<?php
/**
 * SMTP Email Test via Web
 * Akses: http://localhost/dst-recruitment/test_mail.php
 * HAPUS file ini setelah testing selesai!
 */

define('ROOTPATH', __DIR__ . '/');
define('APPPATH',  __DIR__ . '/app/');

// Load .env
$envPath = __DIR__ . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k); $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '' && getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
}

// Load PHPMailer
$phpmailerPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
require_once $phpmailerPath . 'PHPMailer.php';
require_once $phpmailerPath . 'SMTP.php';
require_once $phpmailerPath . 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mailHost     = getenv('MAIL_HOST')     ?: 'mail.karirdigdaya.web.id';
$mailPort     = (int)(getenv('MAIL_PORT') ?: 465);
$mailUser     = getenv('MAIL_USERNAME') ?: '';
$mailPass     = getenv('MAIL_PASSWORD') ?: '';
$mailFrom     = getenv('MAIL_FROM')     ?: $mailUser;
$mailFromName = getenv('MAIL_FROM_NAME')?: 'DST Recruitment';

$target = $_GET['to'] ?? '';
$sent   = false;
$error  = '';
$debug  = '';

if ($target !== '' && filter_var($target, FILTER_VALIDATE_EMAIL)) {
    $mail = new PHPMailer(true);
    ob_start();
    try {
        $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host       = 'ssl://' . $mailHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUser;
        $mail->Password   = $mailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAutoTLS = false;
        $mail->Port       = $mailPort;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        $mail->SMTPOptions = [
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];
        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($target);
        $mail->isHTML(true);
        $mail->Subject = 'Test Email - DST Recruitment';
        $mail->Body    = '<h2>Test berhasil!</h2><p>Email ini dikirim dari server DST Recruitment. Jika menerima ini, berarti konfigurasi SMTP berfungsi dengan baik.</p>';
        $mail->AltBody = 'Test berhasil! Email ini dikirim dari server DST Recruitment.';
        $mail->send();
        $sent = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    $debug = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SMTP Test - DST Recruitment</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 20px; }
        .box  { border: 1px solid #ccc; border-radius: 8px; padding: 16px; margin: 12px 0; }
        .ok   { background: #e8f5e9; border-color: #4caf50; }
        .fail { background: #ffebee; border-color: #f44336; }
        .info { background: #e3f2fd; border-color: #2196f3; }
        pre   { background: #f5f5f5; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
        input[type=email], button { padding: 10px; font-size: 14px; }
        button { background: #0f5e5e; color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔧 SMTP Test — DST Recruitment</h1>

    <div class="box info">
        <strong>Konfigurasi SMTP saat ini:</strong><br>
        Host: <code>ssl://<?= htmlspecialchars($mailHost) ?></code> | Port: <code><?= $mailPort ?></code><br>
        Username: <code><?= htmlspecialchars($mailUser) ?></code><br>
        Password: <code><?= $mailPass ? str_repeat('*', strlen($mailPass)) : '(kosong!)' ?></code><br>
        From: <code><?= htmlspecialchars($mailFrom) ?></code>
    </div>

    <?php if ($sent): ?>
        <div class="box ok">
            <strong>✅ Email BERHASIL dikirim ke <?= htmlspecialchars($target) ?>!</strong><br>
            Silakan cek inbox (dan folder Spam) pada email tersebut.
        </div>
    <?php elseif ($error): ?>
        <div class="box fail">
            <strong>❌ Gagal mengirim email:</strong><br>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="GET">
        <p><label><strong>Kirim test email ke:</strong></label><br>
        <input type="email" name="to" value="<?= htmlspecialchars($target) ?>" placeholder="email@gmail.com" style="width:300px">
        <button type="submit">Kirim Sekarang</button></p>
    </form>

    <?php if ($debug): ?>
        <h3>📋 Debug Log SMTP:</h3>
        <pre><?= htmlspecialchars($debug) ?></pre>
    <?php endif; ?>

    <p style="color:#999; font-size:12px;">⚠️ Hapus file <code>test_mail.php</code> setelah testing selesai.</p>
</body>
</html>
