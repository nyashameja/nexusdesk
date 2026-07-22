<?php

declare(strict_types=1);

/**
 * Protected web cron endpoint.
 *
 * For hosts without CLI cron, schedule a URL fetch instead (every 30 minutes):
 *   curl -s "https://ops.example.com/cron.php?token=SECRET&job=sync"
 *
 * Access requires the CRON_SECRET (compared in constant time). No session,
 * no user — this runs the same read-only sync service as the CLI runner.
 */

use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Services\Whm\SyncService;

/** @var Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$secret   = (string) Config::get('app.security.cron_secret', '');
$provided = (string) ($_GET['token'] ?? '');

if ($secret === '' || !hash_equals($secret, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$job = (string) ($_GET['job'] ?? 'sync');

switch ($job) {
    case 'sync':
        /** @var SyncService $sync */
        $sync   = $container->get(SyncService::class);
        $result = $sync->syncAll();
        echo json_encode([
            'job'     => 'sync',
            'status'  => $result->status(),
            'summary' => $result->summary(),
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown job']);
}
