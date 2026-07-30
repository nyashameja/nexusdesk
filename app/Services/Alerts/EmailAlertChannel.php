<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * Email delivery channel. Recipients and sender come from config/alerts.php.
 */
final class EmailAlertChannel implements AlertChannelInterface
{
    /**
     * @param array{to:array<int,string>, from:string, from_name:string} $config
     */
    public function __construct(
        private Mailer $mailer,
        private array $config,
        private string $appHost,
    ) {
    }

    private string $lastError = '';

    public function name(): string
    {
        return 'email';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['to']);
    }

    public function notify(string $subject, string $body): bool
    {
        $from = $this->config['from'] !== '' ? $this->config['from'] : ('noreply@' . ($this->appHost ?: 'localhost'));

        $ok = $this->mailer->send(
            $this->config['to'],
            $subject,
            $body,
            $from,
            $this->config['from_name'] ?? 'Paragon HostOps'
        );

        $this->lastError = $ok ? '' : 'PHP mail() returned failure — check the server mail log.';

        return $ok;
    }

    /**
     * Email has no richer representation than the plain digest it is given.
     *
     * @param array<int, Alert> $alerts
     */
    public function notifyAlerts(array $alerts, string $subject, string $plainBody): bool
    {
        return $this->notify($subject, $plainBody);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }
}
