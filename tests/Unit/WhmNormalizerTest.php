<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\WhmNormalizer;
use PHPUnit\Framework\TestCase;

final class WhmNormalizerTest extends TestCase
{
    private WhmNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new WhmNormalizer();
    }

    public function test_account_is_normalised_from_listaccts_entry(): void
    {
        $raw = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/whm/listaccts.json'), true);
        $active = $this->normalizer->account($raw['data']['acct'][0]);

        $this->assertSame('paragon1', $active['username']);
        $this->assertSame('acmecorp.co.za', $active['domain']);
        $this->assertSame('Business-10GB', $active['package']);
        $this->assertSame(0, $active['suspended']);
        $this->assertNull($active['suspend_reason']);
        $this->assertSame(4210, $active['disk_used_mb']);
        $this->assertSame(10240, $active['disk_limit_mb']);
        $this->assertSame('2024-03-12', $active['whm_created_at']);
    }

    public function test_suspended_account_keeps_reason(): void
    {
        $raw = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/whm/listaccts.json'), true);
        $suspended = $this->normalizer->account($raw['data']['acct'][2]);

        $this->assertSame(1, $suspended['suspended']);
        $this->assertSame('Non-payment', $suspended['suspend_reason']);
    }

    public function test_partial_account_does_not_error(): void
    {
        $normalised = $this->normalizer->account(['user' => 'partial1']);

        $this->assertSame('partial1', $normalised['username']);
        $this->assertSame('', $normalised['domain']);
        $this->assertSame(0, $normalised['disk_used_mb']);
        $this->assertNull($normalised['package']);
    }

    public function test_bandwidth_is_converted_to_megabytes(): void
    {
        $bw = $this->normalizer->bandwidth(['user' => 'paragon2', 'totalbytes' => '48318382080', 'limit' => '53687091200']);

        $this->assertSame('paragon2', $bw['username']);
        $this->assertSame(46080, $bw['bandwidth_used_mb']);
        $this->assertSame(51200, $bw['bandwidth_limit_mb']);
    }

    public function test_package_normalisation_handles_unlimited(): void
    {
        $pkg = $this->normalizer->package([
            'name' => 'Business-10GB', 'QUOTA' => '10240', 'BWLIMIT' => '512000',
            'MAXADDON' => '10', 'MAXSUB' => 'unlimited', 'MAXPOP' => 'unlimited',
        ]);

        $this->assertSame('Business-10GB', $pkg['name']);
        $this->assertSame(10240, $pkg['disk_quota_mb']);
        $this->assertSame(512000, $pkg['bandwidth_mb']);
        $this->assertSame(10, $pkg['max_addon']);
        $this->assertNull($pkg['max_sub']);
        $this->assertNull($pkg['max_email']);
    }

    public function test_ssl_status_bands(): void
    {
        $this->assertSame('valid', $this->normalizer->sslStatus(90));
        $this->assertSame('expiring', $this->normalizer->sslStatus(20));
        $this->assertSame('expired', $this->normalizer->sslStatus(-3));
        $this->assertSame('unknown', $this->normalizer->sslStatus(null));
    }

    public function test_ssl_record_from_vhost(): void
    {
        $future = time() + (120 * 86400);
        $ssl = $this->normalizer->ssl([
            'servername' => 'acmecorp.co.za',
            'crt' => [
                'not_after' => $future,
                'not_before' => time() - 86400,
                'issuer.commonName' => 'R3',
                'domains' => ['acmecorp.co.za', 'www.acmecorp.co.za'],
            ],
        ]);

        $this->assertSame('acmecorp.co.za', $ssl['domain']);
        $this->assertSame('R3', $ssl['issuer']);
        $this->assertSame('valid', $ssl['status']);
        $this->assertSame('acmecorp.co.za, www.acmecorp.co.za', $ssl['covered_hosts']);
    }
}
