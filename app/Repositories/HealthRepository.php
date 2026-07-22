<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Gathers the inputs the HealthScoreService needs and persists computed
 * scores + their factor breakdown.
 */
final class HealthRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Per-account scoring inputs, assembled from the cache and local records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function accountInputs(int $limit = 500): array
    {
        $limit = max(1, min($limit, 2000));
        return $this->db->all(
            "SELECT
                a.id, a.domain, a.username, a.suspended,
                CASE WHEN u.disk_limit_mb > 0 THEN ROUND(u.disk_used_mb / u.disk_limit_mb * 100, 1) END AS disk_percent,
                CASE WHEN u.bandwidth_limit_mb > 0 THEN ROUND(u.bandwidth_used_mb / u.bandwidth_limit_mb * 100, 1) END AS bandwidth_percent,
                (SELECT MIN(s.days_remaining) FROM whm_ssl_certificates s WHERE s.account_id = a.id) AS ssl_days,
                (SELECT COUNT(*) FROM whm_ssl_certificates s WHERE s.account_id = a.id) AS ssl_count,
                (SELECT MIN(DATEDIFF(d.expires_at, UTC_DATE())) FROM domains d WHERE d.account_id = a.id AND d.deleted_at IS NULL AND d.expires_at IS NOT NULL) AS domain_days,
                (SELECT sub.payment_status FROM subscriptions sub WHERE sub.account_id = a.id AND sub.deleted_at IS NULL ORDER BY sub.id DESC LIMIT 1) AS payment_status
             FROM whm_accounts a
        LEFT JOIN whm_account_usage u ON u.account_id = a.id
            WHERE a.deleted_at IS NULL
         ORDER BY a.domain
            LIMIT {$limit}"
        );
    }

    /**
     * Normalise a raw inputs row into the HealthScoreService input shape.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function toEvaluatorInput(array $row): array
    {
        return [
            'suspended'         => (int) ($row['suspended'] ?? 0),
            'disk_percent'      => $row['disk_percent'] !== null ? (float) $row['disk_percent'] : null,
            'bandwidth_percent' => $row['bandwidth_percent'] !== null ? (float) $row['bandwidth_percent'] : null,
            'ssl_days'          => $row['ssl_days'] !== null ? (int) $row['ssl_days'] : null,
            'ssl_present'       => (int) ($row['ssl_count'] ?? 0) > 0,
            'domain_days'       => $row['domain_days'] !== null ? (int) $row['domain_days'] : null,
            'payment_status'    => $row['payment_status'] ?? null,
        ];
    }

    /**
     * Persist a computed score and its factors for a subject.
     *
     * @param array<int, array{factor:string,impact:int,detail:string}> $factors
     */
    public function persist(string $subjectType, int $subjectId, ?int $score, string $band, array $factors): void
    {
        $this->db->execute(
            'INSERT INTO health_scores (subject_type, subject_id, score, band, computed_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE score = VALUES(score), band = VALUES(band), computed_at = UTC_TIMESTAMP()',
            [$subjectType, $subjectId, $score, $band]
        );

        $id = (int) ($this->db->first(
            'SELECT id FROM health_scores WHERE subject_type = ? AND subject_id = ?',
            [$subjectType, $subjectId]
        )['id'] ?? 0);

        if ($id === 0) {
            return;
        }

        $this->db->execute('DELETE FROM health_score_factors WHERE health_score_id = ?', [$id]);
        foreach ($factors as $f) {
            $this->db->execute(
                'INSERT INTO health_score_factors (health_score_id, factor, impact, detail) VALUES (?, ?, ?, ?)',
                [$id, $f['factor'], $f['impact'], $f['detail']]
            );
        }
    }
}
