<?php

declare(strict_types=1);

/**
 * Zoho Books cache sync. Add to cPanel cron hourly:
 *   0 * * * * php /home/USER/nexusdesk/cron/zoho_sync.php >> /home/USER/nexusdesk/storage/logs/cron.log 2>&1
 *
 * Pulls invoices, quotes, credit notes and payments into the local cache so
 * portal finance pages render instantly and survive Zoho outages.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Services\Zoho\ZohoBooksService;

$service = App::container()->get(ZohoBooksService::class);
if (!$service->isConnected()) {
    fwrite(STDOUT, sprintf("[%s] Zoho not connected; skipping.\n", date('c')));
    exit(0);
}

$count = $service->sync();
fwrite(STDOUT, sprintf("[%s] Zoho sync: %d document(s)\n", date('c'), $count));
