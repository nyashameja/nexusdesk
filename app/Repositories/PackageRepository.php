<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for cached WHM packages.
 */
final class PackageRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed> $data Normalised package fields.
     * @return bool True when a new package row was created.
     */
    public function upsert(int $serverId, array $data): bool
    {
        $existing = $this->db->first(
            'SELECT id FROM whm_packages WHERE server_id = ? AND name = ? LIMIT 1',
            [$serverId, $data['name']]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE whm_packages SET disk_quota_mb = ?, bandwidth_mb = ?, max_addon = ?, max_sub = ?,
                        max_email = ?, updated_at = UTC_TIMESTAMP(), deleted_at = NULL WHERE id = ?',
                [$data['disk_quota_mb'], $data['bandwidth_mb'], $data['max_addon'], $data['max_sub'], $data['max_email'], (int) $existing['id']]
            );
            return false;
        }

        $this->db->execute(
            'INSERT INTO whm_packages (server_id, name, disk_quota_mb, bandwidth_mb, max_addon, max_sub, max_email, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$serverId, $data['name'], $data['disk_quota_mb'], $data['bandwidth_mb'], $data['max_addon'], $data['max_sub'], $data['max_email']]
        );
        return true;
    }
}
