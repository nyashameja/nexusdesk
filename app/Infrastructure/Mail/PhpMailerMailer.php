<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Infrastructure\Logging\LoggerInterface;

/**
 * PHPMailer-backed SMTP mailer. Degrades gracefully if the PHPMailer package
 * is not yet installed (logs and returns false) so the app still boots during
 * early setup / in environments without vendor dependencies.
 */
final class PhpMailerMailer implements MailerInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = []
    ): bool {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $this->logger->warning('PHPMailer not installed; email not sent.', [
                'to' => $toEmail, 'subject' => $subject,
            ]);
            return false;
        }

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = (string) $this->config['host'];
            $mailer->Port = (int) $this->config['port'];
            $encryption = (string) ($this->config['encryption'] ?? 'tls');
            if (($this->config['username'] ?? '') !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = (string) $this->config['username'];
                $mailer->Password = (string) $this->config['password'];
            }
            if ($encryption !== 'none') {
                $mailer->SMTPSecure = $encryption;
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom(
                (string) $this->config['from']['address'],
                (string) $this->config['from']['name']
            );
            $mailer->addAddress($toEmail, $toName);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?? strip_tags($htmlBody);

            foreach ($attachments as $attachment) {
                $mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
            }

            $mailer->send();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Email send failed: ' . $e->getMessage(), ['to' => $toEmail]);
            return false;
        }
    }
}
