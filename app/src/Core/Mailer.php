<?php
/**
 * ZACO Assets - Email Service
 * 
 * @author    Mahmoud Fouad <mahmoud.a.fouad2@gmail.com>
 * @copyright Copyright (c) 2024-<?= date('Y') ?> Mahmoud Fouad
 * @license   Proprietary - All Rights Reserved
 */
declare(strict_types=1);

namespace Zaco\Core;

final class Mailer
{
    public static function enabled(): bool
    {
        $config = $GLOBALS['config'] ?? null;
        if (!$config instanceof Config) {
            return false;
        }

        $enabled = (bool)$config->get('mail.enabled', false);
        if (!$enabled) {
            return false;
        }

        // Avoid fatal errors if Composer deps are not installed.
        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }

    /**
     * @param string|array<int,string> $to
     */
    public static function send(string|array $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $config = $GLOBALS['config'];
        if (!$config instanceof Config) {
            return false;
        }

        $host = trim((string)$config->get('mail.smtp.host', ''));
        $fromEmail = trim((string)$config->get('mail.from_email', ''));
        $fromName = (string)$config->get('mail.from_name', (string)$config->get('app.name', ''));

        if ($host === '' || $fromEmail === '') {
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;

            $mail->SMTPAuth = (bool)$config->get('mail.smtp.auth', true);
            if ($mail->SMTPAuth) {
                $username = (string)$config->get('mail.smtp.username', '');
                $password = (string)$config->get('mail.smtp.password', '');

                if (trim($username) === '') {
                    $envUser = getenv('ZACO_MAIL_SMTP_USERNAME');
                    if (is_string($envUser) && trim($envUser) !== '') {
                        $username = $envUser;
                    }
                }

                if (trim($password) === '') {
                    $envPass = getenv('ZACO_MAIL_SMTP_PASSWORD');
                    if (is_string($envPass) && trim($envPass) !== '') {
                        $password = $envPass;
                    }
                }

                $mail->Username = $username;
                $mail->Password = $password;
            }

            $enc = strtolower(trim((string)$config->get('mail.smtp.encryption', 'tls')));
            if ($enc === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enc === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
            }

            $mail->Port = (int)$config->get('mail.smtp.port', 587);
            $mail->Timeout = (int)$config->get('mail.smtp.timeout', 10);

            $mail->setFrom($fromEmail, $fromName);

            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $addr) {
                $addr = trim((string)$addr);
                if ($addr === '') continue;
                $mail->addAddress($addr);
            }

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;

            if ($textBody === null || trim($textBody) === '') {
                $textBody = html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8');
            }
            $mail->AltBody = $textBody;

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            // Do not block user flows; just log.
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    public static function absoluteUrl(string $path): string
    {
        $config = $GLOBALS['config'] ?? null;
        $publicUrl = '';
        if ($config instanceof Config) {
            $publicUrl = trim((string)$config->get('app.public_url', ''));
        }

        // Prefer configured public URL (works in CLI too)
        if ($publicUrl !== '') {
            $base = rtrim($publicUrl, '/');
            $bp = '';
            if ($config instanceof Config) {
                $bp = rtrim((string)$config->get('app.base_path', ''), '/');
            }
            $p = '/' . ltrim($path, '/');
            return $base . ($bp !== '' ? $bp : '') . $p;
        }

        // Fallback to request host (web only)
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $forwardedProto = mb_strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $protocol = ($https || $forwardedProto === 'https') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return $path;
        }

        $bp = Http::basePath();
        $p = '/' . ltrim($path, '/');
        return $protocol . '://' . $host . $bp . $p;
    }
}
