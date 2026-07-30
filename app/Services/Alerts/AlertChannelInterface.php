<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * A delivery channel for alerts (email and Telegram now; SMS/Slack later).
 *
 * Channels receive the structured alerts and decide how to render them, so
 * detection logic in AlertService stays free of formatting concerns.
 */
interface AlertChannelInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Send a plain subject/body pair. Used for test alerts and by channels
     * that have no richer representation.
     */
    public function notify(string $subject, string $body): bool;

    /**
     * Send a batch of detected alerts. Implementations may render them however
     * suits the medium; $subject and $plainBody are a ready-made plain-text
     * fallback for channels that do not format their own.
     *
     * @param array<int, Alert> $alerts
     */
    public function notifyAlerts(array $alerts, string $subject, string $plainBody): bool;

    /**
     * Human-readable reason the most recent send failed, or '' when the last
     * send succeeded. Surfaced in Settings so misconfiguration is diagnosable
     * without server access.
     */
    public function lastError(): string;
}
