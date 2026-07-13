<?php

declare(strict_types=1);

namespace App\Middleware;

/** Manager area: managers and admins. */
final class EnsureManagerMiddleware extends RoleGuardMiddleware
{
    protected function allowedRoles(): array
    {
        return ['manager', 'administrator'];
    }
}
