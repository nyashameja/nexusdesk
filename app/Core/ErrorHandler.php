<?php

declare(strict_types=1);

namespace App\Core;

use App\Infrastructure\Logging\LoggerInterface;
use Throwable;

/**
 * Global error/exception handler. Logs everything; shows a detailed trace in
 * local/debug mode and a friendly branded page in production. Never leaks
 * stack traces or secrets to end users in production.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        $this->logger?->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, (string) $e . PHP_EOL);
            return;
        }

        $this->renderResponse($e, $status)->send();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->logger?->error('Fatal: ' . $error['message'], $error);
            if (!headers_sent()) {
                $exception = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                $this->renderResponse($exception, 500)->send();
            }
        }
    }

    private function renderResponse(Throwable $e, int $status): Response
    {
        // JSON for API paths.
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with((string) $path, '/api/')) {
            $payload = ['error' => ['code' => 'server_error', 'message' => 'An unexpected error occurred.']];
            if ($this->debug) {
                $payload['error']['debug'] = ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            }
            return new JsonResponse($payload, $status);
        }

        if ($this->debug) {
            $html = '<pre style="padding:20px;font:13px monospace;white-space:pre-wrap;">'
                . htmlspecialchars($e::class . ': ' . $e->getMessage() . "\n\n"
                    . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString())
                . '</pre>';
            return new Response($html, $status);
        }

        try {
            return View::make('errors/' . ($status === 404 ? '404' : '500'), ['status' => $status], 'layouts/error');
        } catch (Throwable) {
            return new Response('<h1>' . $status . '</h1><p>Something went wrong.</p>', $status);
        }
    }
}
