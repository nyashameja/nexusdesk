<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Core\Database;

/**
 * Base for MySQL repositories. Holds the Database and small shared helpers.
 * All SQL in the application lives in these classes — always via prepared,
 * parameter-bound statements on the Database wrapper.
 */
abstract class MySqlRepository
{
    public function __construct(protected readonly Database $db)
    {
    }

    protected function ipToBinary(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }
}
