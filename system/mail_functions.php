<?php
// system/mail_functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Sender e-mail via SMTP (Gmail)
 */
function sendSystemMail($pdo, $toEmails, $subject, $message) {
    // 1. Hent SMTP indstillinger fra databasen
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%' OR setting_key IN ('mail_sender_name', 'mail_sender_email', 'site_name')");
    $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $mail = new PHPMailer(true);

    try {
        // Server indstillinger
        $mail->isSMTP();
        $mail->Host       = $s['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $s['smtp_user'] ?? '';
        $mail->Password   = $s['smtp_pass'] ?? ''; // Dit App Password
        $mail->SMTPSecure = $s['smtp_encryption'] ?? 'ssl';
        $mail->Port       = $s['smtp_port'] ?? 465;
        $mail->CharSet    = 'UTF-8';

        // Afsender
        $fromEmail = !empty($s['smtp_user']) ? $s['smtp_user'] : ($s['mail_sender_email'] ?? 'no-reply@egsol.dk');
        $fromName  = $s['mail_sender_name'] ?? ($s['site_name'] ?? 'Domusius');
        $mail->setFrom($fromEmail, $fromName);

        // Modtagere (Håndterer både enkelt-mail og array af mails)
        if (is_array($toEmails)) {
            foreach ($toEmails as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($toEmails);
        }

        // Indhold
        $mail->isHTML(false); // Vi sender ren tekst som standard
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Hvis det fejler, kan vi logge fejlen (valgfrit)
        error_log("Mail fejl: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Specifik funktion til Admin-notifikation om ny bruger
 */
function sendAdminNotification($pdo, $newUsername) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('admin_notification_email', 'admin_mail_mode', 'site_name')");
    $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $mode = $cfg['admin_mail_mode'] ?? 'single';
    $siteName = $cfg['site_name'] ?? 'Domusius';
    
    $subject = "Ny bruger afventer godkendelse på $siteName";
    $message = "Hej,\n\nEn ny bruger med navnet '$newUsername' har oprettet en konto og afventer din godkendelse i systemet.";

    if ($mode === 'all') {
        $adminStmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'");
        $recipients = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $recipients = $cfg['admin_notification_email'] ?? '';
    }

    if (!empty($recipients)) {
        return sendSystemMail($pdo, $recipients, $subject, $message);
    }
    return false;
}