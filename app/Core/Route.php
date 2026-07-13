<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\MiddlewareInterface;

/**
 * A single registered route: method, compiled path pattern, handler, and the
 * middleware that guard it.
 */
final class Route
{
    private string $pattern;
    /** @var string[] */
    private array $paramNames = [];
    /** @var array<class-string<MiddlewareInterface>> */
    private array $middleware = [];
    private ?string $name = null;

    /**
     * @param array{0:class-string,1:string}|callable $handler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly mixed $handler,
    ) {
        $this->compile();
    }

    private function compile(): void
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $m): string {
            $this->paramNames[] = $m[1];
            return '([^/]+)';
        }, $this->path);
        $this->pattern = '#^' . $pattern . '$#';
    }

    /** @return array<string,string>|null Matched params, or null if no match. */
    public function match(string $method, string $path): ?array
    {
        if ($this->method !== $method) {
            return null;
        }
        if (!preg_match($this->pattern, $path, $matches)) {
            return null;
        }
        array_shift($matches);
        $params = [];
        foreach ($this->paramNames as $i => $paramName) {
            $params[$paramName] = $matches[$i] ?? '';
        }
        return $params;
    }

    /** @param array<class-string<MiddlewareInterface>> $middleware */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /** @return array<class-string<MiddlewareInterface>> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }
}
