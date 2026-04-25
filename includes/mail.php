<?php

declare(strict_types=1);

function app_send_smtp_mail(
    string $to,
    string $subject,
    string $htmlBody,
    string $plainBody,
    string $replyToEmail = '',
    string $replyToName = ''
): bool {
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('SMTP mail error: PHPMailer class not found.');
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = trim((string)(defined('SMTP_HOST') ? SMTP_HOST : '')); 
        $mail->Port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
        $mail->SMTPAuth = true;
        $mail->Username = trim((string)(defined('SMTP_USERNAME') ? SMTP_USERNAME : '')); 
        $mail->Password = trim((string)(defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '')); 

        $encryption = strtolower(trim((string)(defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls')));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls' || $encryption === 'starttls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if ($mail->Host === '' || $mail->Username === '' || $mail->Password === '') {
            error_log('SMTP mail error: SMTP settings are incomplete.');
            return false;
        }

        $fromEmail = trim((string)(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@cantsaria.ru')); 
        $fromName = (string)(defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Канцария');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('SMTP mail error: invalid SMTP_FROM_EMAIL value.');
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('SMTP mail error: invalid recipient address.');
            return false;
        }
        $mail->setFrom($fromEmail, $fromName);

        if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
        }

        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;

        $sent = $mail->send();
        if ($sent && in_array(strtolower((string)(getenv('SMTP_LOG_SUCCESS') ?: '0')), ['1', 'true', 'yes'], true)) {
            error_log(sprintf('SMTP mail sent: to=%s subject=%s', $to, $subject));
        }
        return $sent;
    } catch (Throwable $e) {
        error_log(sprintf(
            'SMTP mail error: %s [host=%s port=%d encryption=%s user=%s from=%s]',
            $e->getMessage(),
            (string)(defined('SMTP_HOST') ? SMTP_HOST : ''),
            (int)(defined('SMTP_PORT') ? SMTP_PORT : 0),
            (string)(defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : ''),
            (string)(defined('SMTP_USERNAME') ? SMTP_USERNAME : ''),
            (string)(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '')
        ));
        return false;
    }
}
