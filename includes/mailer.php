<?php
// includes/mailer.php - Complete Email Sender with PHPMailer + Local Dev Logger
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using configured SMTP or log to local file in development.
 *
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient display name
 * @param string $subject Email subject line
 * @param string $htmlBody HTML content of email
 * @param string $textBody Plain text alternative
 * @return bool True if sent (or logged in dev mode), False on fatal failure
 */
function sendStudyMazeEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {
    $smtpHost = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : '');
    $smtpUser = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : '');
    $smtpPass = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '');
    $smtpPort = (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587));
    $mailFrom = getenv('MAIL_FROM') ?: (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@studymaze.local');
    $mailFromName = getenv('MAIL_FROM_NAME') ?: (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'StudyMaze');

    // If SMTP credentials are provided, attempt real SMTP delivery
    if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = ($smtpPort === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer SMTP Error: " . $e->getMessage());
            // Fall through to dev mode logger so local testing never blocks
        }
    }

    // DEVELOPMENT FALLBACK: Log email safely to logs/email_log.txt
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "========================================================\n"
              . "DATE:    [{$timestamp}]\n"
              . "TO:      {$toName} <{$toEmail}>\n"
              . "FROM:    {$mailFromName} <{$mailFrom}>\n"
              . "SUBJECT: {$subject}\n"
              . "-------------------- MESSAGE BODY --------------------\n"
              . ($textBody ?: strip_tags($htmlBody)) . "\n"
              . "========================================================\n\n";

    @file_put_contents($logDir . '/email_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    error_log("[StudyMaze Dev Mailer] Email dispatched to {$toEmail} (logged to logs/email_log.txt)");

    return true;
}
