<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\MiddlewareInterface;
use RuntimeException;

/**
 * Route registry + dispatcher. Supports route groups (shared prefix and
 * middleware) and named routes for URL generation.
 */
final class Router
{
    /** @var Route[] */
    private array $routes = [];
    private string $groupPrefix = '';
    /** @var array<class-string<MiddlewareInterface>> */
    private array $groupMiddleware = [];

    public function get(string $path, mixed $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, mixed $handler): Route
    {
        $fullPath = $this->groupPrefix . $path;
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath === '/') {
            $fullPath = '/';
        }
        $route = new Route($method, $fullPath, $handler);
        if ($this->groupMiddleware !== []) {
            $route->middleware($this->groupMiddleware);
        }
        $this->routes[] = $route;
        return $route;
    }

    /**
     * @param array{prefix?:string,middleware?:array<class-string<MiddlewareInterface>>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . ($attributes['prefix'] ?? '');
        $this->groupMiddleware = array_merge($previousMiddleware, $attributes['middleware'] ?? []);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * @return array{route:Route,params:array<string,string>}|null
     */
    public function match(Request $request): ?array
    {
        $path = $request->path();
        $method = $request->method();
        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);
            if ($params !== null) {
                return ['route' => $route, 'params' => $params];
            }
        }
        return null;
    }

    public function pathMatchesAnyMethod(string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route->match($route->getMethod(), $path) !== null) {
                return true;
            }
        }
        return false;
    }

    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                $path = $route->getPath();
                foreach ($params as $key => $value) {
                    $path = str_replace('{' . $key . '}', (string) $value, $path);
                }
                return $path;
            }
        }
        throw new RuntimeException("No route named [$name].");
    }
}
