<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Kernel;
use App\Core\Request;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Boots the real HTTP kernel and dispatches the DB-free /health route,
 * asserting the router, container, middleware pipeline, and JSON response all
 * work end-to-end. Runs in a separate process so the app's global error/
 * exception handlers don't interfere with the test runner.
 */
final class HealthEndpointTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_health_endpoint_returns_ok_json(): void
    {
        /** @var Kernel $kernel */
        $kernel = require dirname(__DIR__, 2) . '/app/bootstrap.php';

        $request = new Request(
            'GET',
            '/health',
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
            [],
            []
        );

        ob_start();
        $kernel->handle($request)->send();
        $body = (string) ob_get_clean();

        $payload = json_decode($body, true);
        $this->assertIsArray($payload);
        $this->assertSame('ok', $payload['status'] ?? null);
    }
}
