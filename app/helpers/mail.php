<?php
/**
 * Mail Helper - DST Recruitment
 * Uses PHPMailer to send emails via Gmail SMTP
 */

require_once ROOTPATH . 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once ROOTPATH . 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once ROOTPATH . 'vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email via Gmail SMTP
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @return array{success: bool, message: string}
 */
function sendMail(string $to, string $subject, string $htmlBody): array {
    require_once APPPATH . 'Config/Mail.php';

    $mailUsername = MAIL_USERNAME;
    $mailPassword = MAIL_PASSWORD;

    if (empty($mailUsername) || empty($mailPassword)) {
        return ['success' => false, 'message' => 'SMTP credentials not configured'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
