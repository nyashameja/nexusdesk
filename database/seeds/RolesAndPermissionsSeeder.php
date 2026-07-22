<?php

declare(strict_types=1);

namespace ParagonHostOps\Database\Seeds;

use ParagonHostOps\Core\Database;

/**
 * Seeds the four Version 1 roles, the permission catalogue, and the role →
 * permission grants. Idempotent: safe to run repeatedly.
 */
final class RolesAndPermissionsSeeder
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Permission catalogue: slug => human name (grouped by prefix).
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'dashboard.view'   => 'View dashboard',
        'accounts.view'    => 'View hosting accounts',
        'clients.view'     => 'View clients',
        'clients.manage'   => 'Create / edit clients',
        'domains.view'     => 'View domains',
        'domains.manage'   => 'Create / edit domains',
        'ssl.view'         => 'View SSL centre',
        'email.view'       => 'View email centre',
        'wordpress.view'   => 'View WordPress registry',
        'wordpress.manage' => 'Manage WordPress registry',
        'uptime.view'      => 'View uptime monitors',
        'uptime.manage'    => 'Manage uptime monitors',
        'security.view'    => 'View security centre',
        'finance.view'     => 'View financial information',
        'finance.manage'   => 'Manage financial records',
        'reports.view'     => 'View reports',
        'reports.export'   => 'Export reports (incl. financial)',
        'health.view'      => 'View health scores',
        'sync.view'        => 'View synchronisation',
        'sync.run'         => 'Run synchronisation',
        'audit.view'       => 'View audit logs',
        'settings.view'    => 'View settings & test WHM',
        'users.manage'     => 'Manage application users',
        'roles.manage'     => 'Manage roles',
    ];

    /**
     * Role definitions. super_admin is granted every permission implicitly in
     * code, but we still record its grants for completeness.
     *
     * @var array<string, array{name:string, description:string, permissions:array<int,string>|string}>
     */
    private const ROLES = [
        'super_admin' => [
            'name'        => 'Super Administrator',
            'description' => 'Full access to the internal application.',
            'permissions' => '*',
        ],
        'admin' => [
            'name'        => 'Administrator',
            'description' => 'Operational access to hosting, clients, domains and reports.',
            'permissions' => [
                'dashboard.view', 'accounts.view', 'clients.view', 'clients.manage',
                'domains.view', 'domains.manage', 'ssl.view', 'email.view',
                'wordpress.view', 'wordpress.manage', 'uptime.view', 'uptime.manage',
                'security.view', 'finance.view', 'reports.view', 'reports.export',
                'health.view', 'sync.view', 'sync.run', 'audit.view',
            ],
        ],
        'support_viewer' => [
            'name'        => 'Support Viewer',
            'description' => 'Read-only technical support view. No financial data.',
            'permissions' => [
                'dashboard.view', 'accounts.view', 'clients.view', 'domains.view',
                'ssl.view', 'email.view', 'wordpress.view', 'uptime.view',
                'security.view', 'health.view', 'sync.view',
            ],
        ],
        'finance_viewer' => [
            'name'        => 'Finance Viewer',
            'description' => 'Billing and financial view. No WHM credentials or technical settings.',
            'permissions' => [
                'dashboard.view', 'clients.view', 'finance.view', 'reports.view',
                'reports.export', 'health.view',
            ],
        ],
    ];

    public function run(): void
    {
        // Permissions
        foreach (self::PERMISSIONS as $slug => $name) {
            $group = explode('.', $slug)[0];
            $this->db->execute(
                'INSERT INTO permissions (slug, name, `group`, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), `group` = VALUES(`group`)',
                [$slug, $name, $group]
            );
        }

        // Roles + grants
        foreach (self::ROLES as $slug => $def) {
            $this->db->execute(
                'INSERT INTO roles (slug, name, description, created_at, updated_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), updated_at = UTC_TIMESTAMP()',
                [$slug, $def['name'], $def['description']]
            );

            $roleId = (int) ($this->db->first('SELECT id FROM roles WHERE slug = ?', [$slug])['id'] ?? 0);
            if ($roleId === 0) {
                continue;
            }

            $permissionSlugs = $def['permissions'] === '*'
                ? array_keys(self::PERMISSIONS)
                : $def['permissions'];

            foreach ($permissionSlugs as $permSlug) {
                $permId = (int) ($this->db->first('SELECT id FROM permissions WHERE slug = ?', [$permSlug])['id'] ?? 0);
                if ($permId === 0) {
                    continue;
                }
                $this->db->execute(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                    [$roleId, $permId]
                );
            }
        }

        echo "  - Seeded " . count(self::PERMISSIONS) . " permissions and " . count(self::ROLES) . " roles.\n";
    }
}
