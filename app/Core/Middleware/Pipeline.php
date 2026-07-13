<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Runs a request through an ordered stack of middleware, then the destination.
 * Middleware are resolved from the container (so they can have dependencies).
 */
final class Pipeline
{
    /** @var array<class-string<MiddlewareInterface>> */
    private array $middleware = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param array<class-string<MiddlewareInterface>> $middleware */
    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * @param Closure(Request):Response $destination
     */
    public function run(Request $request, Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (Closure $next, string $middlewareClass): Closure {
                return function (Request $request) use ($next, $middlewareClass): Response {
                    /** @var MiddlewareInterface $instance */
                    $instance = $this->container->get($middlewareClass);
                    return $instance->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }
}
