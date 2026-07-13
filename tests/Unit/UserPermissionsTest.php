<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

final class UserPermissionsTest extends TestCase
{
    private function makeUser(string $roleSlug, array $permissions = []): User
    {
        return new User(
            id: 1, roleId: 1, companyId: null, firstName: 'Jane', lastName: 'Cole',
            email: 'jane@acme.com', passwordHash: 'x', isActive: true,
            roleSlug: $roleSlug, permissions: $permissions
        );
    }

    public function test_admin_has_every_permission(): void
    {
        $admin = $this->makeUser('administrator');
        $this->assertTrue($admin->can('anything.at.all'));
        $this->assertTrue($admin->isStaff());
    }

    public function test_agent_permission_is_scoped(): void
    {
        $agent = $this->makeUser('agent', ['tickets.view', 'tickets.reply']);
        $this->assertTrue($agent->can('tickets.reply'));
        $this->assertFalse($agent->can('users.manage'));
        $this->assertTrue($agent->isStaff());
        $this->assertFalse($agent->isCustomer());
    }

    public function test_role_home_paths(): void
    {
        $this->assertSame('/admin', $this->makeUser('administrator')->homePath());
        $this->assertSame('/desk', $this->makeUser('agent')->homePath());
        $this->assertSame('/portal', $this->makeUser('customer')->homePath());
    }

    public function test_initials_and_full_name(): void
    {
        $user = $this->makeUser('customer');
        $this->assertSame('Jane Cole', $user->fullName());
        $this->assertSame('JC', $user->initials());
    }
}
