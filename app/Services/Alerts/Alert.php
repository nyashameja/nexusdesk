<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * A single detected alert condition.
 *
 * Immutable and free of formatting concerns: detection (AlertService) builds
 * these, and the channel formatters decide how to render them. Only fields
 * that are actually known are populated — formatters skip empty ones rather
 * than printing "unknown".
 */
final class Alert
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_INFO     = 'info';

    /**
     * @param string      $key       Stable de-duplication key (category:subject:bucket).
     * @param string      $category  ssl | domain | disk | bandwidth
     * @param string      $severity  critical | warning | info
     * @param string      $title     Short headline, e.g. "SSL certificate expiring".
     * @param string      $summary   One-line plain-text summary (email + in-app).
     * @param string|null $domain    Affected domain.
     * @param string|null $client    Client display name.
     * @param string|null $account   cPanel username.
     * @param string|null $package   Hosting package.
     * @param string|null $current   Current value, e.g. "87%" or "8.7 GB of 10 GB".
     * @param string|null $threshold Threshold crossed, e.g. "80%" or "5 days".
     * @param string|null $dueDate   Expiry/due date as Y-m-d.
     * @param int|null    $days      Days remaining (negative when overdue).
     * @param string|null $link      App-relative path to the affected record.
     * @param string|null $action    Recommended next step.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $category,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $summary,
        public readonly ?string $domain = null,
        public readonly ?string $client = null,
        public readonly ?string $account = null,
        public readonly ?string $package = null,
        public readonly ?string $current = null,
        public readonly ?string $threshold = null,
        public readonly ?string $dueDate = null,
        public readonly ?int $days = null,
        public readonly ?string $link = null,
        public readonly ?string $action = null,
    ) {
    }

    /**
     * Short human reference derived from the de-dup key, e.g. #ALT-4F2A19.
     * Stable for the same condition across runs, which makes it usable when
     * someone quotes an alert back to you.
     */
    public function reference(): string
    {
        return '#ALT-' . strtoupper(substr(hash('crc32b', $this->key) . '000000', 0, 6));
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }
}
