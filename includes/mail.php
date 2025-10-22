<?php
/**
 * Mail Helper with SMTP Support
 * Uses authenticated SMTP when configured, falls back to PHP mail()
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/smtp_mailer.php';

function sendEmail(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Get settings from database (admin configured) or fallback to config
    $smtpHost = AppSettings::get('smtp_host', SMTP_HOST ?? '');
    $smtpPort = (int) AppSettings::get('smtp_port', SMTP_PORT ?? 587);
    $smtpUser = AppSettings::get('smtp_user', SMTP_USER ?? '');
    $smtpPass = AppSettings::get('smtp_pass', SMTP_PASS ?? '');
    $fromEmail = AppSettings::get('from_email', FROM_EMAIL ?? 'noreply@example.com');
    $fromName = AppSettings::get('from_name', FROM_NAME ?? 'Notifications');

    // Try authenticated SMTP if credentials are provided
    if ($smtpHost && $smtpUser && $smtpPass) {
        try {
            $mailer = new SimpleSMTPMailer($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName);
            return $mailer->send($toEmail, $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("SMTP failed, falling back to mail(): " . $e->getMessage());
        }
    }

    // Fallback to PHP mail() with basic headers
    $boundary = md5(uniqid((string) mt_rand(), true));
    $headers  = '';
    $headers .= 'From: ' . encodeAddress($fromEmail, $fromName) . "\r\n";
    $headers .= 'Reply-To: ' . $fromEmail . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";

    $plain = $textBody ?? strip_tags(preg_replace('/<br\s*\/?>(\r\n)?/i', "\n", $htmlBody));

    $message  = '';
    $message .= '--' . $boundary . "\r\n";
    $message .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
    $message .= $plain . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= '--' . $boundary . '--' . "\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($toEmail, $encodedSubject, $message, $headers);
}

function encodeAddress(string $email, ?string $name = null): string {
    if (!$name) { return $email; }
    $encodedName = '=?UTF-8?B?' . base64_encode($name) . '?=';
    return $encodedName . ' <' . $email . '>';
}

?>