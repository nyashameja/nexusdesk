<?php

declare(strict_types=1);

namespace App\Integrations\Ai;

/**
 * Result of an AI task: the output plus provenance so the UI can show whether
 * it came from a real provider or the stub.
 */
final class AiResult
{
    public function __construct(
        public readonly string $output,
        public readonly bool $stubbed = false,
        public readonly array $meta = [],
    ) {
    }
}
