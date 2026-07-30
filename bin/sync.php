<?php

declare(strict_types=1);

/**
 * CLI synchronisation runner (read-only).
 *
 * Usage:
 *   php bin/sync.php                    Full synchronisation of all data.
 *   php bin/sync.php --account=USER     Refresh a single account.
 *
 * Suitable for a cPanel Cron Job:
 *   0 2 * * * /usr/local/bin/php /home/USER/hostops/bin/sync.php >/dev/null 2>&1
 */

use ParagonHostOps\Core\Container;
use ParagonHostOps\Services\Whm\SyncService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

/** @var Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

/** @var SyncService $sync */
$sync = $container->get(SyncService::class);

$account = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--account=(.+)$/', $arg, $m)) {
        $account = $m[1];
    }
}

echo "Paragon HostOps — synchronisation\n";
echo "---------------------------------\n";

$result = $account !== null ? $sync->syncAccount($account) : $sync->syncAll();

echo "Status : " . strtoupper($result->status()) . "\n";
echo "Summary: " . $result->summary() . "\n";

if ($result->errors !== []) {
    echo "Errors :\n";
    foreach ($result->errors as $error) {
        echo "  - {$error}\n";
    }
}

// A full nightly run also refreshes domain expiry dates (RDAP/WHOIS).
if ($account === null && !in_array('--no-domains', $argv, true)) {
    $domains = $container->get(\ParagonHostOps\Services\Domains\DomainExpiryService::class)->checkAll();
    echo sprintf(
        "Domains: %d checked, %d updated, %d expiring, %d expired\n",
        $domains['checked'], $domains['updated'], $domains['expiring'], $domains['expired']
    );
}

// ...and evaluates alerts (SSL/domain expiry, disk/bandwidth) on the fresh data.
if ($account === null && !in_array('--no-alerts', $argv, true)) {
    $alerts = $container->get(\ParagonHostOps\Services\Alerts\AlertService::class)->run();
    echo sprintf(
        "Alerts : %d new, %d active%s\n",
        $alerts['new'], $alerts['active'], $alerts['emailed'] ? ', email sent' : ''
    );
}

// Non-zero exit for failed runs so cron alerting can detect problems.
exit($result->status() === 'failed' ? 1 : 0);
