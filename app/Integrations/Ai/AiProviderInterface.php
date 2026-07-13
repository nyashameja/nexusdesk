<?php

declare(strict_types=1);

namespace App\Integrations\Ai;

/**
 * Provider-agnostic AI capabilities. The real provider (Anthropic, OpenAI, ...)
 * is wired in later behind this interface via the container binding — no code
 * that calls these methods changes. Until then NullAiProvider returns safe
 * placeholders so the UI and flows are fully built and testable.
 */
interface AiProviderInterface
{
    public function reply(string $conversation, array $context = []): AiResult;
    public function summarize(string $conversation): AiResult;
    public function categorize(string $text): AiResult;
    public function sentiment(string $text): AiResult;
    public function translate(string $text, string $targetLanguage): AiResult;
    public function rewrite(string $text, string $tone = 'professional'): AiResult;
    public function suggestKbArticles(string $text): AiResult;
}
