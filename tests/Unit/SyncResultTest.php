<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\SyncResult;
use PHPUnit\Framework\TestCase;

final class SyncResultTest extends TestCase
{
    public function test_clean_run_is_completed(): void
    {
        $r = new SyncResult();
        $r->processed = 3;
        $r->created = 1;
        $r->updated = 2;

        $this->assertSame('completed', $r->status());
        $this->assertStringContainsString('3 processed', $r->summary());
    }

    public function test_partial_run_when_some_steps_fail_but_data_processed(): void
    {
        $r = new SyncResult();
        $r->processed = 3;
        $r->addError('ssl: unavailable');

        $this->assertSame('partial', $r->status());
        $this->assertSame(1, $r->failed);
    }

    public function test_failed_run_when_nothing_processed(): void
    {
        $r = new SyncResult();
        $r->addError('accounts: permission denied');

        $this->assertSame('failed', $r->status());
    }

    public function test_locked_run_reports_failed_status_and_message(): void
    {
        $r = new SyncResult();
        $r->locked = true;

        $this->assertSame('failed', $r->status());
        $this->assertStringContainsString('already running', $r->summary());
    }

    public function test_counts_shape(): void
    {
        $r = new SyncResult();
        $r->processed = 5;
        $r->created = 2;
        $r->updated = 3;

        $this->assertSame(['processed' => 5, 'created' => 2, 'updated' => 3, 'failed' => 0], $r->counts());
    }
}
