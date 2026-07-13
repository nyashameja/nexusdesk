<?php

declare(strict_types=1);

/**
 * Job queue worker. Add to cPanel cron, e.g. every minute:
 *   * * * * * /usr/local/bin/php /home/USER/nexusdesk/cron/process_jobs.php >> /home/USER/nexusdesk/storage/logs/cron.log 2>&1
 *
 * Processes queued email/AI/sync jobs so web requests stay fast.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Infrastructure\Queue\JobProcessor;

$processor = App::container()->get(JobProcessor::class);
$handled = $processor->run(100);

fwrite(STDOUT, sprintf("[%s] processed %d job(s)\n", date('c'), $handled));
