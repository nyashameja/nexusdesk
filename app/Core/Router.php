<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use ParagonHostOps\Core\Exceptions\HttpException;

/**
 * Front-controller router.
 *
 * Routes are registered with an HTTP method, a path pattern (supporting
 * {param} placeholders), a controller/action pair and an optional list of
 * middleware. Middleware run before the action and may short-circuit the
 * request by returning a Response.
 */
final class Router
{
    /** @var array<int, array{method:string,pattern:string,regex:string,params:array<int,string>,handler:array{0:string,1:string},middleware:array<int,string>}> */
    private array $routes = [];

    public function __construct(private Container $container)
    {
    }

    /**
     * @param array{0:string,1:string} $handler [ControllerClass, method]
     * @param array<int,string>        $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0:string,1:string} $handler
     * @param array<int,string>        $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param array{0:string,1:string} $handler
     * @param array<int,string>        $middleware
     */
    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '([^/]+)';
            },
            '/' . trim($path, '/')
        );

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $path,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();

        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $methodMatched = true;
                continue;
            }

            array_shift($matches);
            $params = array_combine($route['params'], $matches) ?: [];

            return $this->runWithMiddleware($route, $request, $params);
        }

        throw new HttpException($methodMatched ? 405 : 404);
    }

    /**
     * @param array{handler:array{0:string,1:string},middleware:array<int,string>} $route
     * @param array<string, string> $params
     */
    private function runWithMiddleware(array $route, Request $request, array $params): Response
    {
        foreach ($route['middleware'] as $middlewareId) {
            $middleware = $this->resolveMiddleware($middlewareId);
            $result = $middleware->handle($request, $params);

            if ($result instanceof Response) {
                return $result;
            }
        }

        [$class, $action] = $route['handler'];
        return $this->invokeAction($class, $action, $request, $params);
    }

    /**
     * Resolve a middleware id. The "perm:<slug>" convention builds a permission
     * gate bound to the required permission slug.
     */
    private function resolveMiddleware(string $id): \ParagonHostOps\Middleware\MiddlewareInterface
    {
        if (str_starts_with($id, 'perm:')) {
            $permission = substr($id, 5);
            return new \ParagonHostOps\Middleware\PermissionMiddleware(
                $this->container->get(\ParagonHostOps\Services\Auth::class),
                $permission
            );
        }

        /** @var \ParagonHostOps\Middleware\MiddlewareInterface */
        return $this->container->get($id);
    }

    /**
     * @param array<string, string> $params
     */
    private function invokeAction(string $class, string $action, Request $request, array $params): Response
    {

        $controller = $this->container->has($class)
            ? $this->container->get($class)
            : new $class();

        $result = $controller->{$action}($request, $params);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
    }
}
