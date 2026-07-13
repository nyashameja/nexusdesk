<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;

final class MySqlTicketRepository extends MySqlRepository implements TicketRepositoryInterface
{
    private const SELECT = 'SELECT t.*,
            s.slug AS status_slug, s.name AS status_name, s.colour AS status_colour, s.is_resolved,
            p.slug AS priority_slug, p.name AS priority_name, p.colour AS priority_colour, p.weight AS priority_weight,
            d.name AS department_name,
            TRIM(CONCAT(COALESCE(ru.first_name, \'\'), \' \', COALESCE(ru.last_name, \'\'))) AS requester_full_name,
            TRIM(CONCAT(COALESCE(a.first_name, \'\'), \' \', COALESCE(a.last_name, \'\'))) AS agent_name,
            c.name AS company_name
        FROM tickets t
        JOIN ticket_statuses s ON s.id = t.status_id
        JOIN ticket_priorities p ON p.id = t.priority_id
        JOIN departments d ON d.id = t.department_id
        LEFT JOIN users ru ON ru.id = t.requester_id
        LEFT JOIN users a ON a.id = t.assigned_agent_id
        LEFT JOIN companies c ON c.id = t.company_id';

    public function find(int $id): ?Ticket
    {
        $row = $this->db->selectOne(self::SELECT . ' WHERE t.id = ? AND t.deleted_at IS NULL', [$id]);
        return $row ? Ticket::fromRow($row) : null;
    }

    public function findByReference(string $reference): ?Ticket
    {
        $row = $this->db->selectOne(self::SELECT . ' WHERE t.reference = ? AND t.deleted_at IS NULL', [$reference]);
        return $row ? Ticket::fromRow($row) : null;
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $params[] = $perPage;
        $params[] = $offset;
        $rows = $this->db->select(
            self::SELECT . " $where ORDER BY p.weight DESC, t.created_at DESC LIMIT ? OFFSET ?",
            $params
        );
        return array_map(static fn (array $r): Ticket => Ticket::fromRow($r), $rows);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM tickets t
             JOIN ticket_statuses s ON s.id = t.status_id
             JOIN ticket_priorities p ON p.id = t.priority_id
             JOIN departments d ON d.id = t.department_id ' . $where,
            $params
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ['t.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 's.slug = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $conditions[] = 'p.slug = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['department_id'])) {
            $conditions[] = 't.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (!empty($filters['assigned_agent_id'])) {
            $conditions[] = 't.assigned_agent_id = ?';
            $params[] = (int) $filters['assigned_agent_id'];
        }
        if (!empty($filters['requester_id'])) {
            $conditions[] = 't.requester_id = ?';
            $params[] = (int) $filters['requester_id'];
        }
        if (!empty($filters['company_id'])) {
            $conditions[] = 't.company_id = ?';
            $params[] = (int) $filters['company_id'];
        }
        if (isset($filters['open_only']) && $filters['open_only']) {
            $conditions[] = 's.is_open_state = 1';
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(t.subject LIKE ? OR t.reference LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like);
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public function create(array $data): int
    {
        return $this->db->insert('tickets', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('tickets', $data, ['id' => $id]);
    }

    public function nextReferenceNumber(): int
    {
        $max = $this->db->scalar('SELECT COALESCE(MAX(id), 0) FROM tickets');
        return (int) $max + 1;
    }

    public function messages(int $ticketId, bool $includeInternal): array
    {
        $sql = 'SELECT m.*, TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS author_name
                FROM ticket_messages m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.ticket_id = ?';
        $params = [$ticketId];
        if (!$includeInternal) {
            $sql .= ' AND m.is_internal = 0';
        }
        $sql .= ' ORDER BY m.created_at ASC';
        return $this->db->select($sql, $params);
    }

    public function addMessage(array $data): int
    {
        return $this->db->insert('ticket_messages', $data);
    }

    public function addStatusHistory(array $data): void
    {
        $this->db->insert('ticket_status_history', $data);
    }

    public function addTimeEntry(array $data): void
    {
        $this->db->insert('ticket_time_entries', $data);
    }

    public function totalTimeMinutes(int $ticketId): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(minutes), 0) FROM ticket_time_entries WHERE ticket_id = ?',
            [$ticketId]
        );
    }

    public function dashboardCounts(?int $agentId = null): array
    {
        $open = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL AND s.is_open_state = 1'
        );
        $unassigned = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL AND s.is_open_state = 1 AND t.assigned_agent_id IS NULL'
        );
        $resolvedToday = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND DATE(resolved_at) = CURDATE()'
        );
        $mine = 0;
        if ($agentId !== null) {
            $mine = (int) $this->db->scalar(
                'SELECT COUNT(*) FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
                 WHERE t.deleted_at IS NULL AND s.is_open_state = 1 AND t.assigned_agent_id = ?',
                [$agentId]
            );
        }
        $atRisk = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL AND s.is_open_state = 1
               AND t.due_resolution_at IS NOT NULL
               AND t.due_resolution_at <= DATE_ADD(NOW(), INTERVAL 2 HOUR)'
        );
        return compact('open', 'unassigned', 'resolvedToday', 'mine', 'atRisk');
    }

    public function slaAtRisk(int $limit = 10): array
    {
        $rows = $this->db->select(
            self::SELECT . ' WHERE t.deleted_at IS NULL AND s.is_open_state = 1
                AND t.due_resolution_at IS NOT NULL
             ORDER BY t.due_resolution_at ASC LIMIT ?',
            [$limit]
        );
        return array_map(static fn (array $r): Ticket => Ticket::fromRow($r), $rows);
    }
}
