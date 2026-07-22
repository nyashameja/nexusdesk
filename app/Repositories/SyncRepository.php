<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Manages synchronisation run records, errors, history, and a MySQL advisory
 * lock that prevents two full syncs from running at the same time.
 */
final class SyncRepository
{
    private const LOCK_NAME = 'paragon_hostops_sync';

    public function __construct(private Database $db)
    {
    }

    /**
     * Try to acquire the sync lock. Returns false if another sync holds it.
     * The lock auto-releases when the DB connection closes, so a crashed sync
     * cannot deadlock future runs.
     */
    public function acquireLock(int $waitSeconds = 0): bool
    {
        $row = $this->db->first('SELECT GET_LOCK(?, ?) AS ok', [self::LOCK_NAME, $waitSeconds]);
        return (int) ($row['ok'] ?? 0) === 1;
    }

    public function releaseLock(): void
    {
        $this->db->first('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
    }

    public function start(string $type): int
    {
        $this->db->execute(
            'INSERT INTO whm_sync_runs (sync_type, status, started_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$type, 'running']
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array{processed?:int, created?:int, updated?:int, failed?:int} $counts
     */
    public function finish(int $runId, string $status, array $counts, ?string $message = null): void
    {
        $this->db->execute(
            'UPDATE whm_sync_runs SET
                status = ?, records_processed = ?, records_created = ?, records_updated = ?,
                records_failed = ?, message = ?, finished_at = UTC_TIMESTAMP()
             WHERE id = ?',
            [
                $status,
                (int) ($counts['processed'] ?? 0),
                (int) ($counts['created'] ?? 0),
                (int) ($counts['updated'] ?? 0),
                (int) ($counts['failed'] ?? 0),
                $message !== null ? mb_substr($message, 0, 500) : null,
                $runId,
            ]
        );
    }

    public function recordError(int $runId, string $context, string $message): void
    {
        $this->db->execute(
            'INSERT INTO whm_sync_errors (sync_run_id, context, message, created_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$runId, mb_substr($context, 0, 190), mb_substr($message, 0, 500)]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 30): array
    {
        $limit = max(1, min($limit, 200));
        return $this->db->all(
            'SELECT * FROM whm_sync_runs ORDER BY id DESC LIMIT ' . $limit
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errorsFor(int $runId): array
    {
        return $this->db->all(
            'SELECT * FROM whm_sync_errors WHERE sync_run_id = ? ORDER BY id ASC',
            [$runId]
        );
    }
}
