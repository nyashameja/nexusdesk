<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\JobRepositoryInterface;

/**
 * DB-backed job queue. Cron (cron/process_jobs.php) reserves and runs jobs so
 * slow work (email, AI, Zoho sync) never blocks a web request. Reservation is
 * atomic via a conditional UPDATE to be safe if two cron runs overlap.
 */
final class MySqlJobRepository extends MySqlRepository implements JobRepositoryInterface
{
    public function enqueue(string $type, array $payload, string $queue = 'default', int $delaySeconds = 0): int
    {
        return $this->db->insert('jobs', [
            'queue'        => $queue,
            'type'         => $type,
            'payload'      => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'status'       => 'pending',
        ]);
    }

    public function reserveNext(): ?array
    {
        return $this->db->transaction(function (): ?array {
            $row = $this->db->selectOne(
                "SELECT * FROM jobs
                 WHERE status = 'pending' AND available_at <= NOW()
                 ORDER BY id ASC LIMIT 1 FOR UPDATE"
            );
            if ($row === null) {
                return null;
            }
            $this->db->run(
                "UPDATE jobs SET status = 'reserved', reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?",
                [$row['id']]
            );
            $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
            return $row;
        });
    }

    public function markDone(int $id): void
    {
        $this->db->run("UPDATE jobs SET status = 'done' WHERE id = ?", [$id]);
    }

    public function markFailed(int $id, string $error, bool $retry): void
    {
        if ($retry) {
            // Back to pending with a short backoff for another attempt.
            $this->db->run(
                "UPDATE jobs SET status = 'pending', last_error = ?, available_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE id = ?",
                [mb_substr($error, 0, 1000), $id]
            );
        } else {
            $this->db->run("UPDATE jobs SET status = 'failed', last_error = ? WHERE id = ?", [mb_substr($error, 0, 1000), $id]);
        }
    }

    public function pendingCount(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM jobs WHERE status = 'pending'");
    }

    public function failedCount(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM jobs WHERE status = 'failed'");
    }
}
