<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\Support\Units;
use PHPUnit\Framework\TestCase;

final class UnitsTest extends TestCase
{
    public function test_suffixed_sizes_convert_to_megabytes(): void
    {
        $this->assertSame(4210, Units::toMegabytes('4210M'));
        $this->assertSame(10240, Units::toMegabytes('10G'));
        $this->assertSame(1, Units::toMegabytes('1024K'));
        $this->assertSame(1048576, Units::toMegabytes('1T'));
    }

    public function test_bare_number_is_treated_as_megabytes(): void
    {
        $this->assertSame(2048, Units::toMegabytes('2048'));
    }

    public function test_unlimited_and_empty_map_to_zero(): void
    {
        $this->assertSame(0, Units::toMegabytes('unlimited'));
        $this->assertSame(0, Units::toMegabytes(''));
        $this->assertSame(0, Units::toMegabytes(null));
    }

    public function test_bytes_convert_to_megabytes(): void
    {
        $this->assertSame(1, Units::bytesToMegabytes(1048576));
        $this->assertSame(6554, Units::bytesToMegabytes('6871947673'));
        $this->assertSame(0, Units::bytesToMegabytes('not-a-number'));
    }

    public function test_start_date_parsing(): void
    {
        $this->assertSame('2024-03-12', Units::parseStartDate('24/3/12 08:14:02'));
        $this->assertSame('2022-07-19', Units::parseStartDate('22/7/19 14:41:10'));
        $this->assertNull(Units::parseStartDate(''));
    }

    public function test_timestamp_helpers(): void
    {
        $future = time() + (45 * 86400);
        $this->assertSame(date('Y-m-d', $future), Units::timestampToDate($future));
        $this->assertNull(Units::timestampToDate(0));

        $days = Units::daysUntil($future);
        $this->assertNotNull($days);
        $this->assertGreaterThanOrEqual(44, $days);
        $this->assertLessThanOrEqual(45, $days);
    }
}
