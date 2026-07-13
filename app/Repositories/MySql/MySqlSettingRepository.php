<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\SettingRepositoryInterface;

final class MySqlSettingRepository extends MySqlRepository implements SettingRepositoryInterface
{
    public function all(): array
    {
        $rows = $this->db->select('SELECT group_name, key_name, value FROM settings');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['group_name'] . '.' . $row['key_name']] = $this->decode($row['value']);
        }
        return $out;
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $value = $this->db->scalar(
            'SELECT value FROM settings WHERE group_name = ? AND key_name = ?',
            [$group, $key]
        );
        if ($value === false || $value === null) {
            return $default;
        }
        return $this->decode((string) $value);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->db->run(
            'INSERT INTO settings (group_name, key_name, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$group, $key, $encoded]
        );
    }

    public function setMany(array $pairs): void
    {
        $this->db->transaction(function () use ($pairs): void {
            foreach ($pairs as $dottedKey => $value) {
                [$group, $key] = explode('.', $dottedKey, 2);
                $this->set($group, $key, $value);
            }
        });
    }

    private function decode(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
