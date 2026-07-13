<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\EmailTemplateRepositoryInterface;

final class MySqlEmailTemplateRepository extends MySqlRepository implements EmailTemplateRepositoryInterface
{
    public function bySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM email_templates WHERE slug = ? AND is_active = 1', [$slug]);
    }

    public function all(): array
    {
        return $this->db->select('SELECT * FROM email_templates ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM email_templates WHERE id = ?', [$id]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('email_templates', $data, ['id' => $id]);
    }
}
