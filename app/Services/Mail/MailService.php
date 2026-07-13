<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Infrastructure\Logging\LoggerInterface;
use App\Infrastructure\Mail\MailerInterface;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use App\Repositories\Contracts\JobRepositoryInterface;

/**
 * High-level email API. Templated sends are queued (processed by cron) so a
 * slow SMTP server never blocks a web request; rendering happens at send time
 * so template edits take effect immediately. Includes a direct send() for the
 * admin "test email" action.
 */
final class MailService
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $templates,
        private readonly JobRepositoryInterface $jobs,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Queue a templated email. Resolved and sent by the job processor.
     *
     * @param array<string,mixed> $vars
     */
    public function queueTemplate(string $toEmail, string $toName, string $templateSlug, array $vars): void
    {
        $this->jobs->enqueue('email', [
            'to'       => $toEmail,
            'name'     => $toName,
            'template' => $templateSlug,
            'vars'     => $vars,
        ]);
    }

    /**
     * Process one queued email payload (called by the job processor).
     *
     * @param array<string,mixed> $payload
     */
    public function process(array $payload): bool
    {
        $to = (string) ($payload['to'] ?? '');
        $name = (string) ($payload['name'] ?? '');
        if ($to === '') {
            return true; // nothing to do; treat as handled
        }

        if (!empty($payload['template'])) {
            $template = $this->templates->bySlug((string) $payload['template']);
            if ($template === null) {
                $this->logger->warning('Email template not found', ['slug' => $payload['template']]);
                return true;
            }
            $vars = (array) ($payload['vars'] ?? []);
            $subject = TemplateRenderer::render((string) $template['subject'], $vars);
            $html = TemplateRenderer::render((string) $template['body_html'], $vars);
            $text = $template['body_text'] ? TemplateRenderer::render((string) $template['body_text'], $vars) : null;
        } else {
            $subject = (string) ($payload['subject'] ?? '');
            $html = (string) ($payload['html'] ?? '');
            $text = $payload['text'] ?? null;
        }

        return $this->mailer->send($to, $name, $subject, $html, $text);
    }

    /** Send an email immediately (bypasses the queue) — used for test sends. */
    public function send(string $toEmail, string $toName, string $subject, string $html, ?string $text = null): bool
    {
        return $this->mailer->send($toEmail, $toName, $subject, $html, $text);
    }
}
