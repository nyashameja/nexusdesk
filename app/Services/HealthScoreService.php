<?php

declare(strict_types=1);

namespace ParagonHostOps\Services;

/**
 * Transparent client/account health scoring.
 *
 * Each factor contributes a 0..1 sub-score with a fixed weight. The final score
 * is the weighted average over the factors for which data is actually
 * available — so a missing data source lowers confidence rather than silently
 * penalising the account. When too little is known, the result is flagged
 * "incomplete" instead of showing a misleading number.
 *
 * evaluate() is pure (no I/O) so it can be unit-tested exhaustively.
 */
final class HealthScoreService
{
    /** factor => weight */
    private const WEIGHTS = [
        'account_status' => 25,
        'disk'           => 15,
        'bandwidth'      => 10,
        'ssl'            => 20,
        'domain_expiry'  => 15,
        'payment'        => 15,
    ];

    /** Minimum share of total weight that must be available for a real score. */
    private const MIN_COVERAGE = 0.5;

    /**
     * @param array{
     *   suspended:int,
     *   disk_percent:?float,
     *   bandwidth_percent:?float,
     *   ssl_days:?int,
     *   ssl_present:bool,
     *   domain_days:?int,
     *   payment_status:?string
     * } $in
     *
     * @return array{score:?int, band:string, incomplete:bool, factors:array<int,array{factor:string,impact:int,detail:string}>}
     */
    public function evaluate(array $in): array
    {
        $factors = [];
        $weightedSum = 0.0;
        $availableWeight = 0;

        // Account status (always available).
        $status = ((int) $in['suspended'] === 1) ? 0.0 : 1.0;
        $this->add($factors, $weightedSum, $availableWeight, 'account_status',
            $status, $in['suspended'] ? 'Account is suspended' : 'Account is active');

        // Disk usage.
        if ($in['disk_percent'] !== null) {
            $sub = $this->usageScore((float) $in['disk_percent']);
            $this->add($factors, $weightedSum, $availableWeight, 'disk', $sub,
                'Disk at ' . round((float) $in['disk_percent']) . '%');
        }

        // Bandwidth usage.
        if ($in['bandwidth_percent'] !== null) {
            $sub = $this->usageScore((float) $in['bandwidth_percent']);
            $this->add($factors, $weightedSum, $availableWeight, 'bandwidth', $sub,
                'Bandwidth at ' . round((float) $in['bandwidth_percent']) . '%');
        }

        // SSL.
        if ($in['ssl_present'] && $in['ssl_days'] !== null) {
            $days = (int) $in['ssl_days'];
            $sub = match (true) {
                $days < 0   => 0.0,
                $days <= 14 => 0.3,
                $days <= 30 => 0.6,
                default     => 1.0,
            };
            $detail = $days < 0 ? 'SSL expired' : "SSL expires in {$days} day(s)";
            $this->add($factors, $weightedSum, $availableWeight, 'ssl', $sub, $detail);
        }

        // Domain expiry (nearest linked domain).
        if ($in['domain_days'] !== null) {
            $days = (int) $in['domain_days'];
            $sub = match (true) {
                $days < 0   => 0.0,
                $days <= 30 => 0.5,
                default     => 1.0,
            };
            $detail = $days < 0 ? 'Domain expired' : "Domain renews in {$days} day(s)";
            $this->add($factors, $weightedSum, $availableWeight, 'domain_expiry', $sub, $detail);
        }

        // Payment status.
        if ($in['payment_status'] !== null && $in['payment_status'] !== '') {
            $sub = match ($in['payment_status']) {
                'paid', 'complimentary' => 1.0,
                'due'                   => 0.8,
                'partial'               => 0.5,
                'overdue', 'suspended'  => 0.0,
                default                 => 0.7,
            };
            $this->add($factors, $weightedSum, $availableWeight, 'payment', $sub,
                'Payment: ' . $in['payment_status']);
        }

        $totalWeight = array_sum(self::WEIGHTS);
        $coverage = $availableWeight / $totalWeight;

        if ($coverage < self::MIN_COVERAGE) {
            return ['score' => null, 'band' => 'incomplete', 'incomplete' => true, 'factors' => $factors];
        }

        $score = (int) round(($weightedSum / $availableWeight) * 100);

        return [
            'score'      => $score,
            'band'       => $this->band($score),
            'incomplete' => false,
            'factors'    => $factors,
        ];
    }

    public function band(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 60 => 'attention',
            $score >= 40 => 'risk',
            default      => 'critical',
        };
    }

    public static function bandLabel(string $band): string
    {
        return match ($band) {
            'excellent'  => 'Excellent',
            'good'       => 'Good',
            'attention'  => 'Needs attention',
            'risk'       => 'At risk',
            'critical'   => 'Critical',
            default      => 'Incomplete',
        };
    }

    private function usageScore(float $percent): float
    {
        return match (true) {
            $percent < 70 => 1.0,
            $percent < 80 => 0.8,
            $percent < 90 => 0.5,
            $percent < 95 => 0.25,
            default       => 0.0,
        };
    }

    /**
     * @param array<int,array{factor:string,impact:int,detail:string}> $factors
     */
    private function add(array &$factors, float &$weightedSum, int &$availableWeight, string $factor, float $sub, string $detail): void
    {
        $weight = self::WEIGHTS[$factor];
        $weightedSum += $sub * $weight;
        $availableWeight += $weight;

        // "impact" = signed contribution vs. a perfect factor, for transparency.
        $factors[] = [
            'factor' => $factor,
            'impact' => (int) round(($sub - 1.0) * $weight),
            'detail' => $detail,
        ];
    }
}
