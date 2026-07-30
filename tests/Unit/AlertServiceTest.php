<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Alerts\AlertService;
use ParagonHostOps\Services\Alerts\Mailer;
use PHPUnit\Framework\TestCase;

final class AlertServiceTest extends TestCase
{
    public function test_day_bucketing_picks_the_smallest_matching_threshold(): void
    {
        $this->assertNull(AlertService::bucketDays(45));      // > 30 days: no alert
        $this->assertSame('30', AlertService::bucketDays(30));
        $this->assertSame('30', AlertService::bucketDays(16));
        $this->assertSame('15', AlertService::bucketDays(15));
        $this->assertSame('15', AlertService::bucketDays(6));
        $this->assertSame('5', AlertService::bucketDays(5));
        $this->assertSame('5', AlertService::bucketDays(0));
        $this->assertSame('expired', AlertService::bucketDays(-1));
    }

    public function test_usage_bucketing(): void
    {
        $this->assertNull(AlertService::bucketUsage(79.9));
        $this->assertSame('80', AlertService::bucketUsage(80));
        $this->assertSame('80', AlertService::bucketUsage(94.9));
        $this->assertSame('95', AlertService::bucketUsage(95));
        $this->assertSame('95', AlertService::bucketUsage(100));
    }

    public function test_mailer_uses_injected_transport_and_validates_recipients(): void
    {
        $sent = [];
        $mailer = new Mailer(function (string $to, string $subject, string $body, string $headers) use (&$sent): bool {
            $sent[] = ['to' => $to, 'subject' => $subject, 'headers' => $headers];
            return true;
        });

        $ok = $mailer->send(['ops@example.com', 'not-an-email', 'second@example.com'], 'Subj', 'Body', 'from@host', 'Paragon HostOps');

        $this->assertTrue($ok);
        $this->assertCount(2, $sent, 'Invalid recipients are dropped.');
        $this->assertSame('ops@example.com', $sent[0]['to']);
        $this->assertStringContainsString('From: "Paragon HostOps" <from@host>', $sent[0]['headers']);
    }

    public function test_mailer_returns_false_with_no_valid_recipients(): void
    {
        $mailer = new Mailer(fn (): bool => true);
        $this->assertFalse($mailer->send(['nope'], 'S', 'B', 'f@h', 'N'));
        $this->assertFalse($mailer->send([], 'S', 'B', 'f@h', 'N'));
    }
}
