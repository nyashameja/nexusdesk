<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

/**
 * Standardised value object wrapping a successful WHM API response.
 *
 * WHM API 1 wraps payloads in a "metadata" envelope plus a "data" object.
 * This class normalises access to both.
 */
final class WhmResponse
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private array $metadata,
        private array $data,
        private array $raw,
    ) {
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function isSuccessful(): bool
    {
        return (int) ($this->metadata['result'] ?? 0) === 1;
    }

    public function reason(): string
    {
        return (string) ($this->metadata['reason'] ?? '');
    }

    /**
     * Fetch a nested value from the data payload using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
