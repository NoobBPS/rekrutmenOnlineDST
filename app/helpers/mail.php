<?php
/**
 * Mail Helper - DST Recruitment
 * Uses PHPMailer to send emails via Gmail SMTP
 */

// Load PHPMailer files (use @ to suppress warnings if files don't exist)
$phpmailerPath = ROOTPATH . 'vendor/phpmailer/phpmailer/src/';
if (is_file($phpmailerPath . 'PHPMailer.php')) {
    require_once $phpmailerPath . 'PHPMailer.php';
}
if (is_file($phpmailerPath . 'SMTP.php')) {
    require_once $phpmailerPath . 'SMTP.php';
}
if (is_file($phpmailerPath . 'Exception.php')) {
    require_once $phpmailerPath . 'Exception.php';
}

/**
 * Send email via Gmail SMTP
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @return array{success: bool, message: string}
 */
function sendMail(string $to, string $subject, string $htmlBody): array {
    // Load config
    if (is_file(APPPATH . 'Config/Mail.php')) {
        require_once APPPATH . 'Config/Mail.php';
    }

    $mailUsername = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
    $mailPassword = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
    $mailFrom = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : $mailUsername;

    $placeholderValues = ['your-email@gmail.com', 'your-app-password', 'change_me'];
    if (
        in_array(strtolower($mailUsername), $placeholderValues, true)
        || in_array($mailPassword, $placeholderValues, true)
    ) {
        return ['success' => false, 'message' => 'SMTP credentials still use placeholder values'];
    }

    if (empty($mailUsername) || empty($mailPassword)) {
        return ['success' => false, 'message' => 'SMTP credentials not configured'];
    }

    // Check if PHPMailer is available after loading config.
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return ['success' => false, 'message' => 'PHPMailer library not available'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailPort = defined('MAIL_PORT') ? (int) MAIL_PORT : 587;
        $mailSecure = strtolower((string) (defined('MAIL_SECURE') ? MAIL_SECURE : 'tls'));

        // Use ssl:// prefix for host on port 465 (WPU-style), otherwise use host as-is
        $rawHost = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com';
        if ($mailPort === 465 || $mailSecure === 'ssl') {
            $mail->Host   = 'ssl://' . ltrim($rawHost, '/');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPAutoTLS = false;
        } elseif (in_array($mailSecure, ['none', 'false', '0', ''], true)) {
            $mail->Host   = $rawHost;
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->Host   = $rawHost;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
        }

        $mail->isSMTP();
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;
        $mail->Port       = $mailPort;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = defined('MAIL_TIMEOUT') ? max(1, (int) MAIL_TIMEOUT) : 30;
        $mail->SMTPKeepAlive = false;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->setFrom(
            $mailFrom,
            defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'DST Recruitment'
        );
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent'];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
