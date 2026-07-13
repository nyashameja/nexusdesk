<?php

declare(strict_types=1);

/**
 * Nightly database backup. Add to cPanel cron:
 *   30 2 * * * php /home/USER/nexusdesk/cron/backup.php >> /home/USER/nexusdesk/storage/logs/cron.log 2>&1
 *
 * Keeps the 10 most recent gzipped SQL dumps in storage/backups.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Services\Backup\BackupService;

$service = App::container()->get(BackupService::class);
$file = $service->create();
$service->prune(10);

fwrite(STDOUT, sprintf("[%s] backup created: %s\n", date('c'), $file));
