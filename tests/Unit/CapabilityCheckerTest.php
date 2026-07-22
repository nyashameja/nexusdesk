<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\WhmApiClient;
use PHPUnit\Framework\TestCase;

final class CapabilityCheckerTest extends TestCase
{
    private function client(FakeTransport $transport): WhmApiClient
    {
        return new WhmApiClient($transport, [
            'host' => 'whm.example.com', 'port' => 2087, 'username' => 'r', 'token' => 't',
            'api_version' => 1, 'retry' => ['max_attempts' => 0, 'base_delay_ms' => 0],
        ]);
    }

    public function test_available_when_function_succeeds(): void
    {
        $checker = new CapabilityChecker($this->client(FakeTransport::ok('{"metadata":{"result":1,"reason":"OK"},"data":{}}')));
        $result = $checker->probe('listaccts');
        $this->assertSame(CapabilityChecker::AVAILABLE, $result['status']);
    }

    public function test_permission_denied_maps_correctly(): void
    {
        $checker = new CapabilityChecker($this->client(FakeTransport::status(403)));
        $result = $checker->probe('showbw');
        $this->assertSame(CapabilityChecker::PERMISSION_DENIED, $result['status']);
    }

    public function test_server_error_maps_correctly(): void
    {
        $checker = new CapabilityChecker($this->client(FakeTransport::timeout()));
        $result = $checker->probe('listpkgs');
        $this->assertSame(CapabilityChecker::SERVER_ERROR, $result['status']);
    }

    public function test_label_is_human_readable(): void
    {
        $this->assertSame('Available', CapabilityChecker::label(CapabilityChecker::AVAILABLE));
        $this->assertSame('Permission denied', CapabilityChecker::label(CapabilityChecker::PERMISSION_DENIED));
        $this->assertSame('Not yet tested', CapabilityChecker::label('anything_else'));
    }
}
