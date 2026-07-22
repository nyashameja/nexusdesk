<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

/**
 * Mutable accumulator describing the outcome of a synchronisation run.
 */
final class SyncResult
{
    public int $processed = 0;
    public int $created   = 0;
    public int $updated   = 0;
    public int $failed    = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, string> Human-readable per-step notes. */
    public array $notes = [];

    public bool $locked = false;

    public function addError(string $message): void
    {
        $this->errors[] = $message;
        $this->failed++;
    }

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /**
     * A run is "completed" only when nothing failed; otherwise "partial"
     * (or "failed" when it could not run at all).
     */
    public function status(): string
    {
        if ($this->locked) {
            return 'failed';
        }
        if ($this->failed > 0) {
            return $this->processed > 0 ? 'partial' : 'failed';
        }
        return 'completed';
    }

    public function summary(): string
    {
        if ($this->locked) {
            return 'Another synchronisation is already running.';
        }
        $parts = [
            "{$this->processed} processed",
            "{$this->created} created",
            "{$this->updated} updated",
        ];
        if ($this->failed > 0) {
            $parts[] = "{$this->failed} failed";
        }
        $summary = implode(', ', $parts);
        if ($this->notes !== []) {
            $summary .= '. ' . implode('; ', $this->notes);
        }
        return $summary;
    }

    /**
     * @return array{processed:int, created:int, updated:int, failed:int}
     */
    public function counts(): array
    {
        return [
            'processed' => $this->processed,
            'created'   => $this->created,
            'updated'   => $this->updated,
            'failed'    => $this->failed,
        ];
    }
}
