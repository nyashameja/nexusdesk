<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\CompanyRepositoryInterface;

final class MySqlCompanyRepository extends MySqlRepository implements CompanyRepositoryInterface
{
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM companies WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    public function paginate(int $page, int $perPage, ?string $search = null): array
    {
        [$where, $params] = $this->filters($search);
        $offset = max(0, ($page - 1) * $perPage);
        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM tickets t WHERE t.company_id = c.id AND t.deleted_at IS NULL) AS ticket_count,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.deleted_at IS NULL) AS contact_count
             FROM companies c $where ORDER BY c.name LIMIT ? OFFSET ?",
            $params
        );
    }

    public function count(?string $search = null): int
    {
        [$where, $params] = $this->filters($search);
        return (int) $this->db->scalar("SELECT COUNT(*) FROM companies c $where", $params);
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function filters(?string $search): array
    {
        $conditions = ['c.deleted_at IS NULL'];
        $params = [];
        if ($search !== null && $search !== '') {
            $conditions[] = 'c.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public function create(array $data): int
    {
        return $this->db->insert('companies', $data);
    }
}
