<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], ['REQUEST_METHOD' => $method], [], []);
    }

    public function test_matches_static_route(): void
    {
        $router = new Router();
        $router->get('/health', ['HealthController', 'index']);

        $match = $router->match($this->request('GET', '/health'));
        $this->assertNotNull($match);
        $this->assertSame([], $match['params']);
    }

    public function test_matches_route_with_parameter(): void
    {
        $router = new Router();
        $router->get('/desk/tickets/{id}', ['TicketController', 'show']);

        $match = $router->match($this->request('GET', '/desk/tickets/1042'));
        $this->assertNotNull($match);
        $this->assertSame('1042', $match['params']['id']);
    }

    public function test_method_mismatch_does_not_match(): void
    {
        $router = new Router();
        $router->post('/login', ['LoginController', 'login']);

        $this->assertNull($router->match($this->request('GET', '/login')));
    }

    public function test_group_applies_prefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/admin'], function (Router $router): void {
            $router->get('/users', ['UserController', 'index'])->name('admin.users');
        });

        $this->assertNotNull($router->match($this->request('GET', '/admin/users')));
        $this->assertSame('/admin/users', $router->url('admin.users'));
    }
}
