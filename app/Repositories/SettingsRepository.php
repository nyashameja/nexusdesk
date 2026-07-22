<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Key/value application settings (non-secret operational preferences).
 *
 * Secrets such as the WHM token are NEVER stored here — they live only in the
 * environment.
 */
final class SettingsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->db->first('SELECT value FROM application_settings WHERE `key` = ? LIMIT 1', [$key]);
        return $row === null ? $default : (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO application_settings (`key`, value, created_at, updated_at)
             VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()',
            [$key, $value]
        );
    }
}
