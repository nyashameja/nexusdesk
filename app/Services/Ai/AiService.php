<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Core\Database;
use App\Integrations\Ai\AiProviderInterface;
use App\Integrations\Ai\AiResult;
use App\Services\Auth\AuthContext;

/**
 * Application-facing AI operations. Delegates to the configured provider
 * (NullAiProvider until a real one is wired via the container binding) and
 * records each request in ai_requests for audit/usage visibility.
 */
final class AiService
{
    private const TASKS = ['summary', 'reply', 'sentiment', 'rewrite', 'translate', 'categorize', 'kb_suggest'];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly Database $db,
    ) {
    }

    public static function tasks(): array
    {
        return self::TASKS;
    }

    /** @param array<string,mixed> $context */
    public function run(string $task, string $input, array $context = [], ?int $entityId = null): AiResult
    {
        $result = match ($task) {
            'summary'    => $this->provider->summarize($input),
            'reply'      => $this->provider->reply($input, $context),
            'sentiment'  => $this->provider->sentiment($input),
            'rewrite'    => $this->provider->rewrite($input, (string) ($context['tone'] ?? 'professional')),
            'translate'  => $this->provider->translate($input, (string) ($context['language'] ?? 'en')),
            'categorize' => $this->provider->categorize($input),
            'kb_suggest' => $this->provider->suggestKbArticles($input),
            default      => new AiResult('Unsupported AI task.', stubbed: true),
        };

        $this->record($task, $result, $entityId);
        return $result;
    }

    private function record(string $task, AiResult $result, ?int $entityId): void
    {
        try {
            $this->db->insert('ai_requests', [
                'user_id'     => AuthContext::id(),
                'task'        => $task,
                'provider'    => $result->meta['provider'] ?? null,
                'entity_type' => $entityId !== null ? 'Ticket' : null,
                'entity_id'   => $entityId,
                'status'      => $result->stubbed ? 'stubbed' : 'ok',
            ]);
        } catch (\Throwable) {
            // Usage logging is best-effort; never block the AI response.
        }
    }
}
