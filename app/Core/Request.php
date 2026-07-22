<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

/**
 * Immutable-ish wrapper around the incoming HTTP request.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function __construct(
        private array $query,
        private array $post,
        private array $server,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        // Support method spoofing via _method for HTML forms.
        if ($method === 'POST' && isset($this->post['_method'])) {
            $spoofed = strtoupper((string) $this->post['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri  = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        return '/' . trim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function ip(): string
    {
        // Honour trusted proxy header only if present; fall back to REMOTE_ADDR.
        $candidates = [
            $this->server['HTTP_CF_CONNECTING_IP'] ?? null,
            $this->server['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $ip) {
            if (is_string($ip) && filter_var(explode(',', $ip)[0], FILTER_VALIDATE_IP)) {
                return trim(explode(',', $ip)[0]);
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        $xrw    = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json')
            || strtolower($xrw) === 'xmlhttprequest'
            || str_starts_with($this->path(), '/api/');
    }
}
