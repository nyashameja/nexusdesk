<?php

declare(strict_types=1);

namespace App\Controllers\Web\Desk;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Ai\AiService;

/**
 * AI assist endpoints for the ticket workspace. Returns JSON consumed by the
 * "🤖 AI" menu. Backed by the configured provider (stubbed until one is wired),
 * so the UX is fully functional today.
 */
final class AiController
{
    public function __construct(
        private readonly AiService $ai,
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    public function run(Request $request, int $id, string $task): JsonResponse
    {
        if (!in_array($task, AiService::tasks(), true)) {
            return JsonResponse::error('bad_task', 'Unknown AI task.', 400);
        }

        $ticket = $this->tickets->find($id);
        if ($ticket === null) {
            return JsonResponse::error('not_found', 'Ticket not found.', 404);
        }

        // Build the conversation transcript for the model.
        $messages = $this->tickets->messages($id, includeInternal: false);
        $transcript = $ticket->subject() . "\n\n";
        foreach ($messages as $m) {
            $transcript .= strtoupper((string) $m['author_type']) . ': ' . strip_tags((string) $m['body_html']) . "\n";
        }

        $result = $this->ai->run($task, $transcript, [], $id);

        return JsonResponse::ok([
            'task'    => $task,
            'output'  => $result->output,
            'stubbed' => $result->stubbed,
        ]);
    }
}
