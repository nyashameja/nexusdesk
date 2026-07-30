<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * A delivery channel for alerts (email now; WhatsApp/SMS/Slack later).
 */
interface AlertChannelInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function notify(string $subject, string $body): bool;
}
