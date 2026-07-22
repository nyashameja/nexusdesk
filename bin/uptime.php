<?php

declare(strict_types=1);

/**
 * CLI uptime-check runner.
 *
 * Usage:  php bin/uptime.php
 *
 * Suitable for a cPanel Cron Job (every 10 minutes):
 *   /usr/local/bin/php /home/USER/hostops/bin/uptime.php >/dev/null 2>&1
 */

use ParagonHostOps\Core\Container;
use ParagonHostOps\Services\Uptime\UptimeService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

/** @var Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

/** @var UptimeService $uptime */
$uptime  = $container->get(UptimeService::class);
$summary = $uptime->checkAll();

echo "Paragon HostOps — uptime run\n";
echo "Checked: {$summary['checked']}, up: {$summary['up']}, down: {$summary['down']}\n";

exit($summary['down'] > 0 ? 1 : 0);
