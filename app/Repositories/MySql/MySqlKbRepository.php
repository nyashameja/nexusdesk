<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\KbRepositoryInterface;

final class MySqlKbRepository extends MySqlRepository implements KbRepositoryInterface
{
    public function categories(bool $publicOnly = true): array
    {
        $where = $publicOnly ? 'WHERE c.is_public = 1' : '';
        return $this->db->select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM kb_articles a
                      WHERE a.category_id = c.id AND a.status = 'published' AND a.deleted_at IS NULL) AS article_count
             FROM kb_categories c $where ORDER BY c.sort_order, c.name"
        );
    }

    public function category(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM kb_categories WHERE slug = ?', [$slug]);
    }

    public function categoryById(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM kb_categories WHERE id = ?', [$id]);
    }

    public function articlesInCategory(int $categoryId, bool $publicOnly = true): array
    {
        $sql = "SELECT id, title, slug, excerpt, views_count, helpful_count, published_at
                FROM kb_articles
                WHERE category_id = ? AND status = 'published' AND deleted_at IS NULL";
        if ($publicOnly) {
            $sql .= ' AND is_public = 1';
        }
        $sql .= ' ORDER BY published_at DESC';
        return $this->db->select($sql, [$categoryId]);
    }

    public function article(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS author_name
                FROM kb_articles a
                JOIN kb_categories c ON c.id = a.category_id
                LEFT JOIN users u ON u.id = a.author_id
                WHERE a.slug = ? AND a.deleted_at IS NULL";
        if ($publishedOnly) {
            $sql .= " AND a.status = 'published'";
        }
        return $this->db->selectOne($sql, [$slug]);
    }

    public function articleById(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM kb_articles WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function incrementViews(int $id): void
    {
        $this->db->run('UPDATE kb_articles SET views_count = views_count + 1 WHERE id = ?', [$id]);
    }

    public function search(string $query, bool $publicOnly = true, int $limit = 20): array
    {
        $visibility = $publicOnly ? 'AND is_public = 1' : '';
        // Prefer FULLTEXT; fall back to LIKE if the term is too short for the index.
        $term = trim($query);
        if (mb_strlen($term) >= 4) {
            $rows = $this->db->select(
                "SELECT id, title, slug, excerpt,
                        MATCH(title, excerpt, body_html) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
                 FROM kb_articles
                 WHERE status = 'published' AND deleted_at IS NULL $visibility
                   AND MATCH(title, excerpt, body_html) AGAINST (? IN NATURAL LANGUAGE MODE)
                 ORDER BY score DESC LIMIT ?",
                [$term, $term, $limit]
            );
            if ($rows !== []) {
                return $rows;
            }
        }
        $like = '%' . $term . '%';
        return $this->db->select(
            "SELECT id, title, slug, excerpt FROM kb_articles
             WHERE status = 'published' AND deleted_at IS NULL $visibility
               AND (title LIKE ? OR excerpt LIKE ?)
             ORDER BY views_count DESC LIMIT ?",
            [$like, $like, $limit]
        );
    }

    public function popular(int $limit = 5, bool $publicOnly = true): array
    {
        $visibility = $publicOnly ? 'AND is_public = 1' : '';
        return $this->db->select(
            "SELECT id, title, slug, views_count FROM kb_articles
             WHERE status = 'published' AND deleted_at IS NULL $visibility
             ORDER BY views_count DESC LIMIT ?",
            [$limit]
        );
    }

    public function related(int $articleId, int $limit = 4): array
    {
        return $this->db->select(
            "SELECT a.id, a.title, a.slug FROM kb_article_related r
             JOIN kb_articles a ON a.id = r.related_id
             WHERE r.article_id = ? AND a.status = 'published' AND a.deleted_at IS NULL
             LIMIT ?",
            [$articleId, $limit]
        );
    }

    public function recordFeedback(int $articleId, bool $helpful, ?int $userId, ?string $comment, ?string $ip): void
    {
        $this->db->transaction(function () use ($articleId, $helpful, $userId, $comment, $ip): void {
            $this->db->insert('kb_article_feedback', [
                'article_id' => $articleId,
                'user_id'    => $userId,
                'was_helpful' => $helpful ? 1 : 0,
                'comment'    => $comment,
                'ip_address' => $this->ipToBinary($ip),
            ]);
            $column = $helpful ? 'helpful_count' : 'unhelpful_count';
            $this->db->run("UPDATE kb_articles SET $column = $column + 1 WHERE id = ?", [$articleId]);
        });
    }

    public function allArticles(int $page, int $perPage, ?string $search = null): array
    {
        [$where, $params] = $this->manageFilters($search);
        $offset = max(0, ($page - 1) * $perPage);
        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->select(
            "SELECT a.id, a.title, a.slug, a.status, a.is_public, a.views_count, a.updated_at,
                    c.name AS category_name
             FROM kb_articles a JOIN kb_categories c ON c.id = a.category_id
             $where ORDER BY a.updated_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countArticles(?string $search = null): int
    {
        [$where, $params] = $this->manageFilters($search);
        return (int) $this->db->scalar("SELECT COUNT(*) FROM kb_articles a $where", $params);
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function manageFilters(?string $search): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $params = [];
        if ($search !== null && $search !== '') {
            $conditions[] = 'a.title LIKE ?';
            $params[] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public function createArticle(array $data): int
    {
        return $this->db->insert('kb_articles', $data);
    }

    public function updateArticle(int $id, array $data): void
    {
        $this->db->update('kb_articles', $data, ['id' => $id]);
    }

    public function deleteArticle(int $id): void
    {
        $this->db->run('UPDATE kb_articles SET deleted_at = NOW() WHERE id = ?', [$id]);
    }

    public function createCategory(array $data): int
    {
        return $this->db->insert('kb_categories', $data);
    }
}
