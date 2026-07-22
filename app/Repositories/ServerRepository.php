<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for the servers table. The sync process always operates against
 * a concrete server row so account uniqueness (server_id + username) holds.
 */
final class ServerRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Ensure a server row exists for the given hostname and return its id.
     * Used as the anchor for cached accounts when detailed server info is
     * unavailable to the token.
     */
    public function ensure(string $hostname, string $name = 'Primary WHM Server'): int
    {
        $hostname = $hostname !== '' ? $hostname : 'unknown-server';

        $row = $this->db->first('SELECT id FROM servers WHERE hostname = ? LIMIT 1', [$hostname]);
        if ($row !== null) {
            return (int) $row['id'];
        }

        $this->db->execute(
            'INSERT INTO servers (name, hostname, created_at, updated_at)
             VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$name, $hostname]
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $info
     */
    public function updateInfo(int $serverId, array $info): void
    {
        $this->db->execute(
            'UPDATE servers SET ip_address = ?, whm_version = ?, os = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [
                $info['ip'] ?? null,
                $info['whm_version'] ?? null,
                $info['os'] ?? null,
                $serverId,
            ]
        );
    }
}
