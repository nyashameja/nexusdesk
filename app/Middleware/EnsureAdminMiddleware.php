<?php

declare(strict_types=1);

namespace App\Middleware;

/** Administrator-only area. */
final class EnsureAdminMiddleware extends RoleGuardMiddleware
{
    protected function allowedRoles(): array
    {
        return ['administrator'];
    }
}
