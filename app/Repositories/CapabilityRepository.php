<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Persists the per-function WHM API capability status recorded by the checker.
 */
final class CapabilityRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(string $function, string $status, string $message): void
    {
        $this->db->execute(
            'INSERT INTO whm_api_capabilities (function_name, status, message, checked_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), checked_at = UTC_TIMESTAMP()',
            [$function, $status, $message]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM whm_api_capabilities ORDER BY function_name');
    }
}
