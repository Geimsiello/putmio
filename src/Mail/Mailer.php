<?php

declare(strict_types=1);

namespace PutMio\Mail;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PutMio\Config;
use PutMio\View;
use RuntimeException;

final class Mailer
{
    public static function isEnabled(): bool
    {
        return (bool) Config::get('smtp.enabled')
            && trim((string) Config::get('smtp.host', '')) !== ''
            && filter_var(Config::get('smtp.from_email', ''), FILTER_VALIDATE_EMAIL);
    }

    public static function sendInvite(string $toEmail, string $inviteUrl): void
    {
        $appName = (string) Config::get('app.name', 'PutMio');
        $expiresHours = 72;

        $html = View::capture('email/invite', [
            'appName' => $appName,
            'inviteUrl' => $inviteUrl,
            'expiresHours' => $expiresHours,
        ]);

        $text = putmio_lang('invite_email_text', [
            'app' => $appName,
            'url' => $inviteUrl,
            'hours' => (string) $expiresHours,
        ]);

        $subject = putmio_lang('invite_email_subject', ['app' => $appName]);

        self::send($toEmail, $subject, $html, $text);
    }

    public static function send(string $toEmail, string $subject, string $htmlBody, string $textBody): void
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('SMTP non configurato');
        }

        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer non installato. Esegui composer install nella cartella putmio.');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) Config::get('smtp.host');
            $mail->Port = (int) Config::get('smtp.port', 587);
            $mail->SMTPAuth = trim((string) Config::get('smtp.user', '')) !== '';
            $mail->Username = (string) Config::get('smtp.user', '');
            $mail->Password = (string) Config::get('smtp.pass', '');
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $port = (int) Config::get('smtp.port', 587);
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port === 587 || $port === 25) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $fromEmail = (string) Config::get('smtp.from_email');
            $fromName = (string) Config::get('smtp.from_name', 'PutMio');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
        } catch (MailException $e) {
            throw new RuntimeException($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(), 0, $e);
        }
    }
}
