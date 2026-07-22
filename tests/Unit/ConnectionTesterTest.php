<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\ConnectionTester;
use ParagonHostOps\Services\Whm\WhmApiClient;
use PHPUnit\Framework\TestCase;

final class ConnectionTesterTest extends TestCase
{
    private function client(FakeTransport $t): WhmApiClient
    {
        return new WhmApiClient($t, [
            'host' => 'whm.example.com', 'port' => 2087, 'username' => 'r', 'token' => 't',
            'api_version' => 1, 'retry' => ['max_attempts' => 0, 'base_delay_ms' => 0],
        ]);
    }

    public function test_successful_test_reports_account_count(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/whm/listaccts.json');
        $tester = new ConnectionTester($this->client(FakeTransport::ok($body)));

        $result = $tester->run();

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['account_count']);
        $this->assertNotEmpty($result['steps']);
    }

    public function test_auth_failure_reports_safe_message(): void
    {
        $tester = new ConnectionTester($this->client(FakeTransport::status(401)));
        $result = $tester->run();

        $this->assertFalse($result['ok']);
        $this->assertNull($result['account_count']);
        $this->assertNotEmpty($result['message']);
        // The safe message must never leak the configured token value.
        $this->assertStringNotContainsString('TESTTOKEN', $result['message']);
    }

    public function test_unconfigured_client_is_reported(): void
    {
        $client = new WhmApiClient(FakeTransport::ok('{}'), ['host' => '', 'username' => '', 'token' => '']);
        $result = (new ConnectionTester($client))->run();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['configured']);
    }
}
