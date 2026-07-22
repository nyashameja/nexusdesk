<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Uptime\UptimeChecker;
use PHPUnit\Framework\TestCase;

final class UptimeCheckerTest extends TestCase
{
    public function test_matching_status_and_fast_response_is_online(): void
    {
        $this->assertSame(UptimeChecker::ONLINE, UptimeChecker::classify(200, 200, 300, null, false, 2000));
    }

    public function test_any_2xx_3xx_counts_as_up_even_if_not_exact(): void
    {
        $this->assertSame(UptimeChecker::ONLINE, UptimeChecker::classify(301, 200, 300, null, false, 2000));
        $this->assertSame(UptimeChecker::ONLINE, UptimeChecker::classify(204, 200, 300, null, false, 2000));
    }

    public function test_slow_response_is_flagged(): void
    {
        $this->assertSame(UptimeChecker::SLOW, UptimeChecker::classify(200, 200, 4500, null, false, 2000));
    }

    public function test_ssl_error_takes_precedence(): void
    {
        $this->assertSame(UptimeChecker::SSL_PROBLEM, UptimeChecker::classify(0, 200, 100, 'cert expired', true, 2000));
    }

    public function test_transport_error_is_offline(): void
    {
        $this->assertSame(UptimeChecker::OFFLINE, UptimeChecker::classify(0, 200, 100, 'could not connect', false, 2000));
    }

    public function test_server_error_status_is_offline(): void
    {
        $this->assertSame(UptimeChecker::OFFLINE, UptimeChecker::classify(500, 200, 100, null, false, 2000));
        $this->assertSame(UptimeChecker::OFFLINE, UptimeChecker::classify(404, 200, 100, null, false, 2000));
    }

    public function test_matching_non_200_expected_status_is_online(): void
    {
        // A monitor expecting 401 (e.g. a protected endpoint) that returns 401.
        $this->assertSame(UptimeChecker::ONLINE, UptimeChecker::classify(401, 401, 200, null, false, 2000));
    }
}
