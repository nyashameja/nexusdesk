<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

interface MailerInterface
{
    /**
     * Send an HTML email (with optional plain-text alternative).
     *
     * @param array<int,array{path:string,name:string}> $attachments
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = []
    ): bool;
}
