<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\Pipeline;

/**
 * The application core. Boots services, dispatches a request through the
 * router + middleware pipeline to a controller, and returns a Response.
 */
final class Kernel
{
    /** @var array<class-string> Global middleware applied to every request. */
    private array $globalMiddleware = [
        \App\Middleware\SecurityHeadersMiddleware::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
    ) {
    }

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);

        if ($match === null) {
            return $this->handleNoMatch($request);
        }

        /** @var Route $route */
        $route = $match['route'];
        $params = $match['params'];

        $middleware = array_merge($this->globalMiddleware, $route->getMiddleware());

        $destination = function (Request $request) use ($route, $params): Response {
            return $this->runHandler($route->getHandler(), $request, $params);
        };

        return (new Pipeline($this->container))
            ->through($middleware)
            ->run($request, $destination);
    }

    private function handleNoMatch(Request $request): Response
    {
        // Path exists but wrong method → 405; otherwise 404.
        if ($request->expectsJson()) {
            return JsonResponse::error('not_found', 'Resource not found.', 404);
        }
        try {
            return View::make('errors/404', ['status' => 404], 'layouts/error')->setStatus(404);
        } catch (\Throwable) {
            return new Response('<h1>404</h1><p>Page not found.</p>', 404);
        }
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<string,string> $params
     */
    private function runHandler(mixed $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);
            $result = $this->callAction($controller, $method, $request, $params);
        } elseif (is_callable($handler)) {
            $result = $handler($request, $params);
        } else {
            throw new \RuntimeException('Invalid route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return new JsonResponse($result);
        }
        return new Response((string) $result);
    }

    /**
     * Invoke a controller action, injecting the Request and named route params
     * by matching parameter names.
     *
     * @param array<string,string> $params
     */
    private function callAction(object $controller, string $method, Request $request, array $params): mixed
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $args[] = $request;
            } elseif (array_key_exists($name, $params)) {
                $value = $params[$name];
                if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                    $value = (int) $value;
                }
                $args[] = $value;
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $reflection->invokeArgs($controller, $args);
    }
}
