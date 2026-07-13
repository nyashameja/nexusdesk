<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\SearchRepositoryInterface;

/**
 * Global search: queries each entity with a LIMIT and returns grouped results.
 * The caller (SearchController) is responsible for permission scoping.
 */
final class MySqlSearchRepository extends MySqlRepository implements SearchRepositoryInterface
{
    public function global(string $query, int $perType = 5): array
    {
        $like = '%' . $query . '%';

        $tickets = $this->db->select(
            "SELECT id, reference, subject FROM tickets
             WHERE deleted_at IS NULL AND (reference LIKE ? OR subject LIKE ?)
             ORDER BY created_at DESC LIMIT ?",
            [$like, $like, $perType]
        );

        $companies = $this->db->select(
            'SELECT id, name FROM companies WHERE deleted_at IS NULL AND name LIKE ? ORDER BY name LIMIT ?',
            [$like, $perType]
        );

        $articles = $this->db->select(
            "SELECT id, title, slug FROM kb_articles
             WHERE deleted_at IS NULL AND status = 'published' AND (title LIKE ? OR excerpt LIKE ?)
             LIMIT ?",
            [$like, $like, $perType]
        );

        $users = $this->db->select(
            "SELECT id, first_name, last_name, email FROM users
             WHERE deleted_at IS NULL AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
             LIMIT ?",
            [$like, $like, $like, $perType]
        );

        return compact('tickets', 'companies', 'articles', 'users');
    }
}
