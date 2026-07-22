<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Records login attempts and supports brute-force throttling / lockout.
 */
final class LoginAttemptRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(string $email, string $ip, bool $successful, string $userAgent): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (email, ip_address, successful, user_agent, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [strtolower(trim($email)), $ip, $successful ? 1 : 0, $userAgent]
        );
    }

    /**
     * Count failed attempts for an email+IP within the given window (minutes).
     */
    public function recentFailures(string $email, string $ip, int $windowMinutes): int
    {
        $row = $this->db->first(
            'SELECT COUNT(*) AS c
               FROM login_attempts
              WHERE successful = 0
                AND (email = ? OR ip_address = ?)
                AND created_at >= (UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [strtolower(trim($email)), $ip, $windowMinutes]
        );

        return (int) ($row['c'] ?? 0);
    }

    public function clearFailures(string $email, string $ip): void
    {
        $this->db->execute(
            'DELETE FROM login_attempts WHERE successful = 0 AND (email = ? OR ip_address = ?)',
            [strtolower(trim($email)), $ip]
        );
    }

    public function lockUser(int $userId, int $minutes): void
    {
        $this->db->execute(
            'UPDATE users SET locked_until = (UTC_TIMESTAMP() + INTERVAL ? MINUTE) WHERE id = ?',
            [$minutes, $userId]
        );
    }
}
