<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SettingRepositoryInterface
{
    /** @return array<string,mixed> All settings keyed "group.key". */
    public function all(): array;
    public function get(string $group, string $key, mixed $default = null): mixed;
    public function set(string $group, string $key, mixed $value): void;
    /** @param array<string,mixed> $pairs group.key => value */
    public function setMany(array $pairs): void;
}
