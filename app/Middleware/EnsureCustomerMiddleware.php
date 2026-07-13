<?php

declare(strict_types=1);

namespace App\Middleware;

/** Client portal: customers only (staff use the desk). */
final class EnsureCustomerMiddleware extends RoleGuardMiddleware
{
    protected function allowedRoles(): array
    {
        return ['customer'];
    }
}
