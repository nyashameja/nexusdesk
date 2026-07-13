<?php

declare(strict_types=1);

namespace App\Middleware;

/** Grants access to the agent desk: agents, managers, admins. */
final class EnsureStaffMiddleware extends RoleGuardMiddleware
{
    protected function allowedRoles(): array
    {
        return ['agent', 'manager', 'administrator'];
    }
}
