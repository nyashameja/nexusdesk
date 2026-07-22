<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Uptime;

use ParagonHostOps\Repositories\NotificationRepository;
use ParagonHostOps\Repositories\UptimeRepository;

/**
 * Orchestrates uptime checks: runs the checker for one or all enabled monitors,
 * records results, and raises a notification when a site transitions to
 * offline / SSL-problem. Designed to run on demand or from cron — no permanent
 * worker is required.
 */
final class UptimeService
{
    public function __construct(
        private UptimeRepository $monitors,
        private UptimeChecker $checker,
        private NotificationRepository $notifications,
    ) {
    }

    /**
     * Check every enabled monitor. Returns a small summary.
     *
     * @return array{checked:int, up:int, down:int}
     */
    public function checkAll(): array
    {
        $checked = 0;
        $up = 0;
        $down = 0;

        foreach ($this->monitors->enabled() as $monitor) {
            $result = $this->checkOne($monitor);
            $checked++;
            $result['is_up'] ? $up++ : $down++;
        }

        return ['checked' => $checked, 'up' => $up, 'down' => $down];
    }

    /**
     * Check a single monitor row and persist the outcome.
     *
     * @param array<string, mixed> $monitor
     * @return array{status:string, status_code:?int, response_ms:int, is_up:bool, error:?string}
     */
    public function checkOne(array $monitor): array
    {
        $previous = (string) ($monitor['current_status'] ?? 'unknown');

        $result = $this->checker->probe((string) $monitor['url'], (int) ($monitor['expected_status'] ?? 200));
        $this->monitors->recordCheck((int) $monitor['id'], $result);

        // Notify on a transition into a bad state (once per unresolved incident).
        $badStates = [UptimeChecker::OFFLINE, UptimeChecker::SSL_PROBLEM];
        if (in_array($result['status'], $badStates, true) && $previous !== $result['status']) {
            $label = (string) ($monitor['label'] ?? $monitor['url']);
            $this->notifications->createOnce(
                'uptime',
                "{$label} is " . ($result['status'] === UptimeChecker::SSL_PROBLEM ? 'reporting an SSL problem' : 'offline'),
                (string) $monitor['url'] . ($result['error'] ? ' — ' . $result['error'] : ''),
                'danger'
            );
        }

        return $result;
    }
}
