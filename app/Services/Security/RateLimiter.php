<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Database;

/**
 * Fixed-window rate limiter backed by the rate_limits table. Used for the API
 * and other abuse-prone endpoints. Returns remaining allowance so callers can
 * emit X-RateLimit-* headers.
 */
final class RateLimiter
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{allowed:bool,remaining:int,limit:int,reset:int,retry_after:int}
     */
    public function hit(string $key, int $limit, int $windowSeconds): array
    {
        $now = time();
        $row = $this->db->selectOne('SELECT * FROM rate_limits WHERE bucket_key = ?', [$key]);

        if ($row === null || strtotime((string) $row['reset_at']) <= $now) {
            $resetAt = date('Y-m-d H:i:s', $now + $windowSeconds);
            $this->db->run(
                'INSERT INTO rate_limits (bucket_key, hits, reset_at) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = 1, reset_at = VALUES(reset_at)',
                [$key, $resetAt]
            );
            return ['allowed' => true, 'remaining' => $limit - 1, 'limit' => $limit, 'reset' => $now + $windowSeconds, 'retry_after' => 0];
        }

        $hits = (int) $row['hits'];
        $reset = strtotime((string) $row['reset_at']);

        if ($hits >= $limit) {
            return ['allowed' => false, 'remaining' => 0, 'limit' => $limit, 'reset' => $reset, 'retry_after' => max(1, $reset - $now)];
        }

        $this->db->run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket_key = ?', [$key]);
        return ['allowed' => true, 'remaining' => $limit - $hits - 1, 'limit' => $limit, 'reset' => $reset, 'retry_after' => 0];
    }
}
