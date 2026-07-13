<?php

declare(strict_types=1);

/**
 * SLA monitor. Add to cPanel cron every 5 minutes (schedule: "5-minute" step,
 * i.e. minute field "*" slash "5"):
 *   php /home/USER/nexusdesk/cron/sla_monitor.php >> /home/USER/nexusdesk/storage/logs/cron.log 2>&1
 *
 * Flags first-response and resolution SLA breaches on open tickets and notifies
 * the assigned agent. Deadlines are stored on the ticket at creation time.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;
use App\Repositories\Contracts\NotificationRepositoryInterface;

$db = App::container()->get(Database::class);
$notifications = App::container()->get(NotificationRepositoryInterface::class);

// Resolution breaches.
$breached = $db->select(
    "SELECT t.id, t.reference, t.assigned_agent_id
     FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
     WHERE t.deleted_at IS NULL AND s.is_open_state = 1 AND s.pauses_sla = 0
       AND t.sla_resolution_breached = 0
       AND t.due_resolution_at IS NOT NULL AND t.due_resolution_at < NOW()"
);

$count = 0;
foreach ($breached as $ticket) {
    $db->run('UPDATE tickets SET sla_resolution_breached = 1 WHERE id = ?', [$ticket['id']]);
    if ($ticket['assigned_agent_id'] !== null) {
        $notifications->create([
            'user_id' => (int) $ticket['assigned_agent_id'],
            'type'    => 'sla.breach',
            'title'   => 'SLA breached: ' . $ticket['reference'],
            'body'    => 'Resolution deadline has passed.',
            'url'     => '/desk/tickets/' . $ticket['id'],
            'icon'    => 'alert',
        ]);
    }
    $count++;
}

fwrite(STDOUT, sprintf("[%s] SLA monitor flagged %d breach(es)\n", date('c'), $count));
