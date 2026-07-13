<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

final class MySqlUserRepository extends MySqlRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $row = $this->db->selectOne(
            'SELECT u.*, r.slug AS role_slug FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->selectOne(
            'SELECT u.*, r.slug AS role_slug FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.deleted_at IS NULL',
            [$email]
        );
        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): User
    {
        $permissions = $this->permissionsForRole((int) $row['role_id']);
        return User::fromRow($row, (string) $row['role_slug'], $permissions);
    }

    public function paginate(int $page, int $perPage, ?string $search = null, ?string $roleSlug = null): array
    {
        [$where, $params] = $this->filters($search, $roleSlug);
        $offset = max(0, ($page - 1) * $perPage);
        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->select(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.is_active, u.last_login_at,
                    r.name AS role_name, r.slug AS role_slug
             FROM users u JOIN roles r ON r.id = u.role_id
             $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public function count(?string $search = null, ?string $roleSlug = null): int
    {
        [$where, $params] = $this->filters($search, $roleSlug);
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id $where",
            $params
        );
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function filters(?string $search, ?string $roleSlug): array
    {
        $conditions = ['u.deleted_at IS NULL'];
        $params = [];
        if ($search !== null && $search !== '') {
            $conditions[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if ($roleSlug !== null && $roleSlug !== '') {
            $conditions[] = 'r.slug = ?';
            $params[] = $roleSlug;
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('users', $data, ['id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->update('users', ['password_hash' => $passwordHash], ['id' => $id]);
    }

    public function recordLogin(int $id, string $ip): void
    {
        $this->db->run(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
            [$this->ipToBinary($ip), $id]
        );
    }

    public function permissionsForRole(int $roleId): array
    {
        $rows = $this->db->select(
            'SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?',
            [$roleId]
        );
        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    public function roleSlug(int $roleId): string
    {
        return (string) $this->db->scalar('SELECT slug FROM roles WHERE id = ?', [$roleId]);
    }
}
