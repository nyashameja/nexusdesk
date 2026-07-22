<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Services\Whm\Exceptions\WhmException;
use Throwable;

/**
 * Application kernel. Owns the request → response lifecycle, applies security
 * headers, and converts exceptions into friendly error pages.
 */
final class App
{
    public function __construct(
        private Container $container,
        private Router $router,
    ) {
    }

    public function run(Request $request): void
    {
        $response = $this->handle($request);
        $this->applySecurityHeaders($response);
        $response->send();
    }

    private function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return $this->renderError($request, $e->statusCode(), $e->getMessage());
        } catch (WhmException $e) {
            $this->log('error', 'Unhandled WHM exception: ' . $e->getMessage());
            return $this->renderError($request, 502, $e->safeMessage());
        } catch (Throwable $e) {
            $this->log('error', 'Unhandled exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $debug   = (bool) config('app.debug', false);
            $message = $debug
                ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                : 'An unexpected error occurred.';

            return $this->renderError($request, 500, $message);
        }
    }

    private function renderError(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }

        try {
            /** @var View $view */
            $view = $this->container->get(View::class);
            $html = $view->render('errors.error', [
                'status'  => $status,
                'message' => $message,
                'appName' => config('app.name'),
                'tagline' => config('app.tagline'),
            ]);
            return Response::html($html, $status);
        } catch (Throwable) {
            return Response::html("<h1>{$status}</h1><p>" . e($message) . '</p>', $status);
        }
    }

    private function applySecurityHeaders(Response $response): void
    {
        $csp = "default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self'; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'";

        $response
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('X-XSS-Protection', '0')
            ->header('Content-Security-Policy', $csp);

        if (config('app.env') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->container->has(Logger::class)) {
            $this->container->get(Logger::class)->{$level}($message, $context);
        }
    }
}
