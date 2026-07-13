<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\LookupRepositoryInterface;

final class MySqlLookupRepository extends MySqlRepository implements LookupRepositoryInterface
{
    public function statuses(): array
    {
        return $this->db->select('SELECT * FROM ticket_statuses ORDER BY sort_order');
    }

    public function priorities(): array
    {
        return $this->db->select('SELECT * FROM ticket_priorities ORDER BY sort_order');
    }

    public function statusBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM ticket_statuses WHERE slug = ?', [$slug]);
    }

    public function priorityBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM ticket_priorities WHERE slug = ?', [$slug]);
    }

    public function defaultStatusId(): int
    {
        return (int) $this->db->scalar('SELECT id FROM ticket_statuses ORDER BY sort_order LIMIT 1');
    }

    public function slaTarget(int $policyId, int $priorityId): ?array
    {
        $row = $this->db->selectOne(
            'SELECT first_response_minutes, resolution_minutes FROM sla_targets
             WHERE sla_policy_id = ? AND priority_id = ?',
            [$policyId, $priorityId]
        );
        if ($row === null) {
            return null;
        }
        return [
            'first_response_minutes' => (int) $row['first_response_minutes'],
            'resolution_minutes'     => (int) $row['resolution_minutes'],
        ];
    }
}
