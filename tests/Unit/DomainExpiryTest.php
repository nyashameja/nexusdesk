<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Domains\DomainExpiryChecker;
use ParagonHostOps\Services\Domains\DomainExpiryService;
use PHPUnit\Framework\TestCase;

final class DomainExpiryTest extends TestCase
{
    public function test_parses_rdap_expiration_and_registrar(): void
    {
        $json = [
            'events' => [
                ['eventAction' => 'registration', 'eventDate' => '2010-03-01T00:00:00Z'],
                ['eventAction' => 'expiration', 'eventDate' => '2027-01-15T00:00:00Z'],
            ],
            'entities' => [
                ['roles' => ['registrar'], 'vcardArray' => ['vcard', [['version', [], 'text', '4.0'], ['fn', [], 'text', 'GoDaddy.com, LLC']]]],
            ],
        ];

        $parsed = DomainExpiryChecker::parseRdap($json);

        $this->assertSame('2027-01-15', $parsed['expires_at']);
        $this->assertSame('GoDaddy.com, LLC', $parsed['registrar']);
    }

    public function test_parse_rdap_without_expiration_returns_null(): void
    {
        $parsed = DomainExpiryChecker::parseRdap(['events' => [['eventAction' => 'last changed', 'eventDate' => '2020-01-01T00:00:00Z']]]);
        $this->assertNull($parsed['expires_at']);
        $this->assertNull($parsed['registrar']);
    }

    public function test_parses_whois_expiry_formats(): void
    {
        $this->assertSame('2026-11-30', DomainExpiryChecker::parseWhoisExpiry("Domain: example.com\nRegistry Expiry Date: 2026-11-30T23:59:59Z\n"));
        $this->assertSame('2025-08-01', DomainExpiryChecker::parseWhoisExpiry("Expiration Date: 2025/08/01\n"));
        $this->assertNull(DomainExpiryChecker::parseWhoisExpiry("No date here at all.\n"));
    }

    public function test_status_derivation(): void
    {
        $future = date('Y-m-d', strtotime('+120 days'));
        $soon   = date('Y-m-d', strtotime('+10 days'));
        $past   = date('Y-m-d', strtotime('-2 days'));

        $this->assertSame('active', DomainExpiryService::statusFor($future));
        $this->assertSame('expiring', DomainExpiryService::statusFor($soon));
        $this->assertSame('expired', DomainExpiryService::statusFor($past));
        $this->assertSame('unknown', DomainExpiryService::statusFor(null));
        $this->assertSame('unknown', DomainExpiryService::statusFor('not-a-date'));
    }
}
