<?php

declare(strict_types=1);

namespace App\Core;

final class JsonResponse extends Response
{
    /** @param mixed $data @param array<string,string> $headers */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        parent::__construct($body ?: 'null', $status, $headers);
    }

    /** @param mixed $data @param array<string,mixed> $meta */
    public static function ok(mixed $data, array $meta = [], int $status = 200): self
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return new self($payload, $status);
    }

    /** @param array<string,mixed> $errors */
    public static function error(string $code, string $message, int $status = 400, array $errors = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($errors !== []) {
            $error['errors'] = $errors;
        }
        return new self(['error' => $error], $status);
    }
}
