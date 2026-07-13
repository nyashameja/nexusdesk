<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Infrastructure\Logging\LoggerInterface;
use App\Repositories\Contracts\JobRepositoryInterface;
use App\Services\Mail\MailService;

/**
 * Reserves and runs queued jobs, dispatching by type to a handler. Invoked by
 * cron/process_jobs.php. Failed jobs retry up to their max_attempts, then are
 * marked failed for admin review.
 */
final class JobProcessor
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Process up to $max jobs; returns the number handled. */
    public function run(int $max = 50): int
    {
        $processed = 0;
        while ($processed < $max) {
            $job = $this->jobs->reserveNext();
            if ($job === null) {
                break;
            }
            $this->handle($job);
            $processed++;
        }
        return $processed;
    }

    /** @param array<string,mixed> $job */
    private function handle(array $job): void
    {
        $id = (int) $job['id'];
        $type = (string) $job['type'];
        $payload = (array) ($job['payload'] ?? []);
        $attempts = (int) ($job['attempts'] ?? 1);
        $maxAttempts = (int) ($job['max_attempts'] ?? 3);

        try {
            $ok = match ($type) {
                'email' => $this->mail->process($payload),
                default => $this->unknown($type),
            };

            if ($ok) {
                $this->jobs->markDone($id);
            } else {
                $this->jobs->markFailed($id, 'Handler returned false', $attempts < $maxAttempts);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Job failed: ' . $e->getMessage(), ['job' => $id, 'type' => $type]);
            $this->jobs->markFailed($id, $e->getMessage(), $attempts < $maxAttempts);
        }
    }

    private function unknown(string $type): bool
    {
        $this->logger->warning('Unknown job type', ['type' => $type]);
        return true; // drop unknown jobs rather than looping forever
    }
}
