<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Alerts\TelegramAlertChannel;
use PHPUnit\Framework\TestCase;

final class TelegramAlertChannelTest extends TestCase
{
    public function test_not_configured_without_token_or_chat_id(): void
    {
        $this->assertFalse((new TelegramAlertChannel(['enabled' => true, 'bot_token' => '', 'chat_id' => '']))->isConfigured());
        $this->assertFalse((new TelegramAlertChannel(['enabled' => false, 'bot_token' => 't', 'chat_id' => 'c']))->isConfigured());
        $this->assertTrue((new TelegramAlertChannel(['enabled' => true, 'bot_token' => 't', 'chat_id' => 'c']))->isConfigured());
    }

    public function test_sends_message_with_expected_payload(): void
    {
        $captured = null;
        $channel = new TelegramAlertChannel(
            ['enabled' => true, 'bot_token' => 'BOTTOKEN', 'chat_id' => '12345'],
            function (string $url, array $params) use (&$captured): array {
                $captured = ['url' => $url, 'params' => $params];
                return ['ok' => true, 'status' => 200];
            }
        );

        $ok = $channel->notify('Subject line', 'Body text');

        $this->assertTrue($ok);
        $this->assertStringContainsString('/botBOTTOKEN/sendMessage', $captured['url']);
        $this->assertSame('12345', $captured['params']['chat_id']);
        $this->assertStringContainsString('Subject line', $captured['params']['text']);
        $this->assertStringContainsString('Body text', $captured['params']['text']);
    }

    public function test_reports_failure_on_api_error(): void
    {
        $channel = new TelegramAlertChannel(
            ['enabled' => true, 'bot_token' => 't', 'chat_id' => 'c'],
            fn (): array => ['ok' => false, 'status' => 400]
        );

        $this->assertFalse($channel->notify('S', 'B'));
    }

    public function test_unconfigured_channel_does_not_send(): void
    {
        $called = false;
        $channel = new TelegramAlertChannel(
            ['enabled' => false, 'bot_token' => '', 'chat_id' => ''],
            function () use (&$called): array { $called = true; return ['ok' => true, 'status' => 200]; }
        );

        $this->assertFalse($channel->notify('S', 'B'));
        $this->assertFalse($called, 'Transport must not be called when unconfigured.');
    }
}
