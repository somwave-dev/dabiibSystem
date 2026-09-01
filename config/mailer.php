<?php
declare(strict_types=1);

/**
 * PHPMailer wrapper.
 *
 * SMTP settings are read from system_settings:
 *   smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_email, smtp_from_name
 * When smtp_host is empty the PHP mail() transport is used and a copy of the
 * message is written to storage/mail.log so local development can still see the OTP.
 */

require_once __DIR__ . '/codes.php';
require_once __DIR__ . '/../includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/../includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../includes/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function clinic_send_mail(string $toEmail, string $toName, string $subject, string $html, string $alt): array
{
    $co = new Codes();
    $db = $co->db;

    $settings = [];
    $stmt = $db->prepare('SELECT setting_key, setting_value FROM system_settings');
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        $stmt->close();
    }
    $db->close();

    $host = trim($settings['smtp_host'] ?? '');
    $port = (int) ($settings['smtp_port'] ?? '') > 0 ? (int) $settings['smtp_port'] : 587;
    $user = trim($settings['smtp_user'] ?? '');
    $pass = (string) ($settings['smtp_pass'] ?? '');
    $fromEmail = trim($settings['smtp_from_email'] ?? '');
    $fromName = trim($settings['smtp_from_name'] ?? '');
    $siteName = trim($settings['site_name'] ?? '') !== '' ? trim($settings['site_name']) : 'Dabiib System';

    if ($fromEmail === '') {
        $fromEmail = 'no-reply@localhost';
    }
    if ($fromName === '') {
        $fromName = $siteName;
    }

    $mail = new PHPMailer(false);
    try {
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = ($user !== '' || $pass !== '');
            if ($mail->SMTPAuth) {
                $mail->Username = $user;
                $mail->Password = $pass;
            }
            $mail->SMTPSecure = $port === 465 ? 'ssl' : 'tls';
            $mail->Timeout = 15;
        } else {
            $mail->isMail();
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $alt;

        $sent = $mail->send();
        if ($sent) {
            return ['ok' => true, 'error' => ''];
        }
        $error = $mail->ErrorInfo;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $sent = false;
    }

    // No SMTP configured: log a local copy (dev fallback) and treat as sent.
    if ($host === '') {
        $logDir = __DIR__ . '/../storage';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . "] To: {$toEmail} | Subject: {$subject}\n{$alt}\n\n";
        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);

        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => $sent, 'error' => (string) ($error ?? 'Unknown mail error')];
}
