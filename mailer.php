<?php
declare(strict_types=1);

require_once __DIR__ . '/app-config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mail_config(): array
{
    $config = app_config();
    $mail = $config['mail'] ?? [];

    return [
        'host' => (string) ($mail['host'] ?? ''),
        'port' => (int) ($mail['port'] ?? 587),
        'username' => (string) ($mail['username'] ?? ''),
        'password' => (string) ($mail['password'] ?? ''),
        'encryption' => (string) ($mail['encryption'] ?? 'tls'),
        'from_email' => (string) ($mail['from_email'] ?? ''),
        'from_name' => (string) ($mail['from_name'] ?? 'TrackMyProperty'),
        'debug' => (bool) ($mail['debug'] ?? false),
    ];
}

function send_app_mail(string $toEmail, string $toName, string $subject, string $bodyText, ?string &$error = null): bool
{
    $config = mail_config();

    if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '' || $config['from_email'] === '') {
        $error = 'SMTP is not configured.';
        return false;
    }

    $phpMailerLoaded = load_phpmailer($error);
    if (!$phpMailerLoaded) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        $encryption = strtolower($config['encryption']);
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;
        $mail->AltBody = $bodyText;
        $mail->isHTML(false);

        if ($config['debug']) {
            $mail->SMTPDebug = 2;
        }

        return $mail->send();
    } catch (Exception $e) {
        $error = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        return false;
    }
}

function load_phpmailer(?string &$error = null): bool
{
    $base = __DIR__ . '/vendor/phpmailer/phpmailer/src';
    $files = [
        $base . '/PHPMailer.php',
        $base . '/SMTP.php',
        $base . '/Exception.php',
    ];

    foreach ($files as $file) {
        if (!is_file($file)) {
            $error = 'PHPMailer is missing. Upload vendor/phpmailer/phpmailer/src files.';
            return false;
        }
    }

    require_once $files[0];
    require_once $files[1];
    require_once $files[2];

    return true;
}
