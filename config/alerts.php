<?php

declare(strict_types=1);

/**
 * Alerting configuration.
 *
 * Email is the only channel enabled in Version 1 (uses PHP mail(), which works
 * out of the box on cPanel). The architecture supports additional channels
 * (e.g. WhatsApp) without changing the alert logic.
 */
$recipients = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('ALERT_EMAIL_TO', ''))
)));

return [
    'enabled' => env_bool('ALERTS_ENABLED', false),

    'email' => [
        'to'        => $recipients,
        'from'      => env('ALERT_EMAIL_FROM', ''), // falls back to noreply@<app-host>
        'from_name' => env('ALERT_EMAIL_FROM_NAME', 'Paragon HostOps'),
    ],

    /**
     * Thresholds (days for expiry, percent for usage). The lowest matching
     * bucket fires exactly once per crossing; alerts re-arm when the condition
     * clears (e.g. a domain is renewed).
     */
    'thresholds' => [
        'domain_days' => [30, 15, 5],
        'ssl_days'    => [30, 15, 5],
        'usage_pct'   => [80, 95],
    ],
];
