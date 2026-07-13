<?php

declare(strict_types=1);

namespace App\Integrations\Ai;

/**
 * Deterministic stub provider. Returns safe placeholder output and never fails,
 * so every AI touchpoint in the UI works before a real provider is connected.
 */
final class NullAiProvider implements AiProviderInterface
{
    public function reply(string $conversation, array $context = []): AiResult
    {
        return $this->stub('Thanks for reaching out — a support agent will follow up shortly. '
            . '(AI suggestions activate once a provider is configured in Settings → AI.)');
    }

    public function summarize(string $conversation): AiResult
    {
        return $this->stub('Summary unavailable — connect an AI provider in Settings → AI to enable '
            . 'automatic thread summaries.');
    }

    public function categorize(string $text): AiResult
    {
        return $this->stub('general');
    }

    public function sentiment(string $text): AiResult
    {
        return $this->stub('neutral');
    }

    public function translate(string $text, string $targetLanguage): AiResult
    {
        return $this->stub($text);
    }

    public function rewrite(string $text, string $tone = 'professional'): AiResult
    {
        return $this->stub($text);
    }

    public function suggestKbArticles(string $text): AiResult
    {
        return $this->stub('');
    }

    private function stub(string $output): AiResult
    {
        return new AiResult($output, stubbed: true, meta: ['provider' => 'null']);
    }
}
