<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\HealthScoreService;
use PHPUnit\Framework\TestCase;

final class HealthScoreServiceTest extends TestCase
{
    private HealthScoreService $svc;

    protected function setUp(): void
    {
        $this->svc = new HealthScoreService();
    }

    /**
     * @return array<string, mixed>
     */
    private function inputs(array $overrides = []): array
    {
        return array_merge([
            'suspended'         => 0,
            'disk_percent'      => 40.0,
            'bandwidth_percent' => 30.0,
            'ssl_days'          => 200,
            'ssl_present'       => true,
            'domain_days'       => 300,
            'payment_status'    => 'paid',
        ], $overrides);
    }

    public function test_healthy_account_scores_excellent(): void
    {
        $r = $this->svc->evaluate($this->inputs());
        $this->assertFalse($r['incomplete']);
        $this->assertSame(100, $r['score']);
        $this->assertSame('excellent', $r['band']);
    }

    public function test_suspended_account_is_penalised(): void
    {
        $r = $this->svc->evaluate($this->inputs(['suspended' => 1]));
        $this->assertLessThan(100, $r['score']);
        // account_status weight 25 of 100 lost -> 75.
        $this->assertSame(75, $r['score']);
        $this->assertSame('good', $r['band']);
    }

    public function test_multiple_problems_drive_score_down(): void
    {
        $r = $this->svc->evaluate($this->inputs([
            'suspended'      => 1,
            'disk_percent'   => 96.0,
            'ssl_days'       => -5,
            'payment_status' => 'overdue',
        ]));
        $this->assertLessThanOrEqual(40, $r['score']);
        $this->assertContains($r['band'], ['risk', 'critical']);
    }

    public function test_incomplete_when_too_little_data(): void
    {
        // Only account_status available (weight 25 < 50% coverage).
        $r = $this->svc->evaluate([
            'suspended'         => 0,
            'disk_percent'      => null,
            'bandwidth_percent' => null,
            'ssl_days'          => null,
            'ssl_present'       => false,
            'domain_days'       => null,
            'payment_status'    => null,
        ]);
        $this->assertTrue($r['incomplete']);
        $this->assertNull($r['score']);
        $this->assertSame('incomplete', $r['band']);
    }

    public function test_factors_are_transparent_with_negative_impacts(): void
    {
        $r = $this->svc->evaluate($this->inputs(['ssl_days' => -1]));
        $ssl = array_values(array_filter($r['factors'], static fn ($f) => $f['factor'] === 'ssl'))[0];
        $this->assertSame(-20, $ssl['impact']); // full SSL weight lost
        $this->assertStringContainsString('expired', strtolower($ssl['detail']));
    }

    public function test_band_labels(): void
    {
        $this->assertSame('Excellent', HealthScoreService::bandLabel('excellent'));
        $this->assertSame('At risk', HealthScoreService::bandLabel('risk'));
        $this->assertSame('Incomplete', HealthScoreService::bandLabel('incomplete'));
    }
}
