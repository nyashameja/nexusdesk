<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\Exceptions\WhmApiException;
use ParagonHostOps\Services\Whm\Exceptions\WhmAuthenticationException;
use ParagonHostOps\Services\Whm\Exceptions\WhmConnectionException;
use ParagonHostOps\Services\Whm\Exceptions\WhmInvalidResponseException;
use ParagonHostOps\Services\Whm\Exceptions\WhmPermissionException;
use ParagonHostOps\Services\Whm\WhmApiClient;
use ParagonHostOps\Services\Whm\WhmTransportInterface;
use PHPUnit\Framework\TestCase;

final class WhmApiClientTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/whm';
    private const SCENARIOS = __DIR__ . '/../fixtures/scenarios';

    private function client(WhmTransportInterface $transport): WhmApiClient
    {
        return new WhmApiClient($transport, [
            'host'        => 'whm.example.com',
            'port'        => 2087,
            'username'    => 'reseller',
            'token'       => 'TESTTOKEN',
            'api_version' => 1,
            'retry'       => ['max_attempts' => 2, 'base_delay_ms' => 0],
        ]);
    }

    public function test_parses_successful_listaccts(): void
    {
        $client = $this->client(FakeTransport::ok((string) file_get_contents(self::FIXTURES . '/listaccts.json')));
        $response = $client->get('listaccts');

        $this->assertTrue($response->isSuccessful());
        $accounts = $response->get('acct');
        $this->assertCount(3, $accounts);
        $this->assertSame('acmecorp.co.za', $accounts[0]['domain']);
    }

    public function test_handles_empty_account_list(): void
    {
        $client = $this->client(FakeTransport::ok((string) file_get_contents(self::SCENARIOS . '/listaccts_empty.json')));
        $response = $client->get('listaccts');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame([], $response->get('acct'));
    }

    public function test_authentication_failure_throws(): void
    {
        $this->expectException(WhmAuthenticationException::class);
        $this->client(FakeTransport::status(401))->get('listaccts');
    }

    public function test_permission_error_via_http_403_throws_permission(): void
    {
        $this->expectException(WhmPermissionException::class);
        $this->client(FakeTransport::status(403))->get('listaccts');
    }

    public function test_permission_error_via_metadata_throws_permission(): void
    {
        $client = $this->client(FakeTransport::ok((string) file_get_contents(self::SCENARIOS . '/permission_denied.json')));
        $this->expectException(WhmPermissionException::class);
        $client->get('listaccts');
    }

    public function test_api_failure_metadata_throws_api_exception(): void
    {
        $client = $this->client(FakeTransport::ok((string) file_get_contents(self::SCENARIOS . '/api_failure.json')));
        $this->expectException(WhmApiException::class);
        $client->get('bogusfunction');
    }

    public function test_invalid_json_throws(): void
    {
        $client = $this->client(FakeTransport::ok((string) file_get_contents(self::SCENARIOS . '/invalid_json.json')));
        $this->expectException(WhmInvalidResponseException::class);
        $client->get('listaccts');
    }

    public function test_timeout_throws_connection_exception(): void
    {
        $this->expectException(WhmConnectionException::class);
        $this->client(FakeTransport::timeout())->get('listaccts');
    }

    public function test_retries_transient_failure_then_succeeds(): void
    {
        $good = FakeTransport::response(200, (string) file_get_contents(self::FIXTURES . '/version.json'));
        $transport = new FakeTransport([
            ['status' => 503, 'body' => '', 'error' => null, 'timedOut' => false, 'connectFailed' => false],
            $good,
        ]);

        $response = $this->client($transport)->get('version');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(2, $transport->calls, 'Client should retry once after a 503.');
    }

    public function test_does_not_retry_authentication_failure(): void
    {
        $transport = new FakeTransport([FakeTransport::response(401, '')]);

        try {
            $this->client($transport)->get('listaccts');
            $this->fail('Expected authentication exception.');
        } catch (WhmAuthenticationException) {
            // expected
        }

        $this->assertSame(1, $transport->calls, 'Auth failures must not be retried.');
    }

    public function test_unconfigured_client_reports_not_configured(): void
    {
        $client = new WhmApiClient(FakeTransport::ok('{}'), ['host' => '', 'username' => '', 'token' => '']);
        $this->assertFalse($client->isConfigured());
    }
}
