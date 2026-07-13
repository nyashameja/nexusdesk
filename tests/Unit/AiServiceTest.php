<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Integrations\Ai\NullAiProvider;
use App\Services\Ai\AiService;
use PHPUnit\Framework\TestCase;

final class AiServiceTest extends TestCase
{
    private function service(): AiService
    {
        // A Database pointed at nowhere: usage logging (best-effort) is
        // swallowed, so run() still returns the provider's result.
        $db = new Database(['host' => '127.0.0.1', 'port' => 1, 'database' => 'none']);
        return new AiService(new NullAiProvider(), $db);
    }

    public function test_known_tasks_return_stubbed_results(): void
    {
        $service = $this->service();
        foreach (['summary', 'reply', 'sentiment', 'rewrite'] as $task) {
            $result = $service->run($task, 'Customer cannot log in.');
            $this->assertTrue($result->stubbed);
            $this->assertNotSame('', $result->output);
        }
    }

    public function test_unknown_task_is_handled(): void
    {
        $result = $this->service()->run('nonsense', 'text');
        $this->assertTrue($result->stubbed);
        $this->assertStringContainsString('Unsupported', $result->output);
    }

    public function test_task_list_is_exposed(): void
    {
        $this->assertContains('summary', AiService::tasks());
        $this->assertContains('sentiment', AiService::tasks());
    }
}
