<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Alerts\Alert;
use ParagonHostOps\Services\Alerts\TelegramAlertChannel;
use ParagonHostOps\Services\Alerts\TelegramMessageFormatter;
use PHPUnit\Framework\TestCase;

final class TelegramAlertChannelTest extends TestCase
{
    /** @var array<int, array{url:string, params:array}> */
    private array $calls = [];

    /**
     * Build a channel whose transport records calls and replays canned
     * responses, so nothing touches the network.
     *
     * @param array<int, array{ok:bool,status:int,error:string}> $responses
     */
    private function channel(array $responses, array $config = []): TelegramAlertChannel
    {
        $this->calls = [];
        $i = 0;

        return new TelegramAlertChannel(
            $config + ['enabled' => true, 'bot_token' => 'BOTTOKEN', 'chat_id' => '12345'],
            function (string $url, array $params) use (&$i, $responses): array {
                $this->calls[] = ['url' => $url, 'params' => $params];
                return $responses[min($i++, count($responses) - 1)];
            },
            null,
            new TelegramMessageFormatter('https://hostops.example.com', 'cpr48-za1'),
            0, // no real sleeping between retries
        );
    }

    private function ok(): array   { return ['ok' => true, 'status' => 200, 'error' => '']; }
    private function err(int $status, string $error = ''): array { return ['ok' => false, 'status' => $status, 'error' => $error]; }

    private function alert(string $key = 'ssl:example.co.za:5'): Alert
    {
        return new Alert(
            key: $key,
            category: 'ssl',
            severity: Alert::SEVERITY_CRITICAL,
            title: 'SSL certificate expires in 5 days',
            summary: 'SSL certificate for example.co.za expires in 5 day(s).',
            domain: 'example.co.za',
            link: '/accounts/123',
        );
    }

    // ---- Configuration --------------------------------------------------

    public function test_requires_enabled_token_and_chat_id(): void
    {
        $this->assertFalse((new TelegramAlertChannel(['enabled' => true, 'bot_token' => '', 'chat_id' => '1']))->isConfigured());
        $this->assertFalse((new TelegramAlertChannel(['enabled' => false, 'bot_token' => 't', 'chat_id' => '1']))->isConfigured());
        $this->assertTrue((new TelegramAlertChannel(['enabled' => true, 'bot_token' => 't', 'chat_id' => '1']))->isConfigured());
    }

    public function test_chat_id_validation_accepts_numeric_group_and_channel_ids(): void
    {
        $this->assertTrue(TelegramAlertChannel::isValidChatId('12345'));
        $this->assertTrue(TelegramAlertChannel::isValidChatId('-1001234567'), 'group ids are negative');
        $this->assertTrue(TelegramAlertChannel::isValidChatId('@mychannel'));
    }

    public function test_chat_id_validation_rejects_malformed_values(): void
    {
        $this->assertFalse(TelegramAlertChannel::isValidChatId(''));
        $this->assertFalse(TelegramAlertChannel::isValidChatId('not-an-id'));
        $this->assertFalse(TelegramAlertChannel::isValidChatId('123abc'));
        $this->assertFalse(TelegramAlertChannel::isValidChatId('@ab'), 'usernames must be long enough');
    }

    public function test_invalid_chat_id_makes_the_channel_unconfigured(): void
    {
        $channel = new TelegramAlertChannel(['enabled' => true, 'bot_token' => 't', 'chat_id' => 'nonsense']);

        $this->assertFalse($channel->isConfigured());
        $this->assertFalse($channel->notify('S', 'B'));
        $this->assertStringContainsString('not configured', $channel->lastError());
    }

    // ---- Sending --------------------------------------------------------

    public function test_sends_with_html_parse_mode_to_the_token_url(): void
    {
        $channel = $this->channel([$this->ok()]);

        $this->assertTrue($channel->notify('Subject line', 'Body text'));
        $this->assertStringContainsString('/botBOTTOKEN/sendMessage', $this->calls[0]['url']);
        $this->assertSame('12345', $this->calls[0]['params']['chat_id']);
        $this->assertSame('HTML', $this->calls[0]['params']['parse_mode']);
        $this->assertStringContainsString('Subject line', $this->calls[0]['params']['text']);
    }

    public function test_plain_notify_escapes_its_input(): void
    {
        $channel = $this->channel([$this->ok()]);
        $channel->notify('A & B', '<script>alert(1)</script>');

        $text = $this->calls[0]['params']['text'];
        $this->assertStringContainsString('A &amp; B', $text);
        $this->assertStringNotContainsString('<script>', $text);
    }

    public function test_single_alert_sends_an_inline_button_to_that_record(): void
    {
        $channel = $this->channel([$this->ok()]);

        $this->assertTrue($channel->notifyAlerts([$this->alert()], 'subject', 'plain'));

        $markup = json_decode($this->calls[0]['params']['reply_markup'], true);
        $this->assertSame('https://hostops.example.com/accounts/123', $markup['inline_keyboard'][0][0]['url']);
    }

    public function test_multiple_alerts_send_one_digest_with_a_list_button(): void
    {
        $channel = $this->channel([$this->ok()]);

        $channel->notifyAlerts([$this->alert('a'), $this->alert('b')], 'subject', 'plain');

        $this->assertCount(1, $this->calls, 'A run must produce a single Telegram message.');
        $this->assertStringContainsString('ALERT SUMMARY', $this->calls[0]['params']['text']);

        $markup = json_decode($this->calls[0]['params']['reply_markup'], true);
        $this->assertSame('https://hostops.example.com/notifications', $markup['inline_keyboard'][0][0]['url']);
    }

    public function test_message_is_truncated_below_the_telegram_limit(): void
    {
        $channel = $this->channel([$this->ok()]);
        $channel->notify('S', str_repeat('x', 9000));

        $this->assertLessThanOrEqual(4000, mb_strlen($this->calls[0]['params']['text']));
    }

    public function test_empty_alert_batch_sends_nothing(): void
    {
        $channel = $this->channel([$this->ok()]);

        $this->assertFalse($channel->notifyAlerts([], 'subject', 'plain'));
        $this->assertSame([], $this->calls);
    }

    // ---- Retry policy ---------------------------------------------------

    public function test_transient_failure_is_retried_then_succeeds(): void
    {
        $channel = $this->channel([$this->err(503, 'temporarily unavailable'), $this->ok()]);

        $this->assertTrue($channel->notify('S', 'B'));
        $this->assertCount(2, $this->calls);
        $this->assertSame('', $channel->lastError());
    }

    public function test_network_error_is_retried(): void
    {
        $channel = $this->channel([$this->err(0, 'could not resolve host'), $this->ok()]);

        $this->assertTrue($channel->notify('S', 'B'));
        $this->assertCount(2, $this->calls);
    }

    public function test_permanent_configuration_error_is_not_retried(): void
    {
        $channel = $this->channel([$this->err(401, 'Unauthorized')]);

        $this->assertFalse($channel->notify('S', 'B'));
        $this->assertCount(1, $this->calls, 'A bad token must not be retried.');
        $this->assertStringContainsString('Unauthorized', $channel->lastError());
    }

    public function test_chat_not_found_is_not_retried(): void
    {
        $channel = $this->channel([$this->err(400, 'Bad Request: chat not found')]);

        $this->assertFalse($channel->notify('S', 'B'));
        $this->assertCount(1, $this->calls);
    }

    public function test_persistent_transient_failure_gives_up_after_max_attempts(): void
    {
        $channel = $this->channel([$this->err(429, 'Too Many Requests')]);

        $this->assertFalse($channel->notify('S', 'B'));
        $this->assertCount(3, $this->calls);
        $this->assertStringContainsString('429', $channel->lastError());
    }

    public function test_successful_send_clears_the_previous_error(): void
    {
        $channel = $this->channel([$this->err(401, 'Unauthorized'), $this->ok()]);

        $channel->notify('S', 'B');
        $this->assertNotSame('', $channel->lastError());

        $channel->notify('S', 'B');
        $this->assertSame('', $channel->lastError());
    }

    // ---- Secret safety --------------------------------------------------

    public function test_bot_token_is_redacted_from_error_text(): void
    {
        $leaky = 'Failed to connect to https://api.telegram.org/bot123456:AAH-SECRET_token/sendMessage';

        $redacted = TelegramAlertChannel::redactUrl($leaky);

        $this->assertStringNotContainsString('AAH-SECRET_token', $redacted);
        $this->assertStringContainsString('/bot[REDACTED]', $redacted);
    }

    public function test_last_error_never_leaks_the_token(): void
    {
        $channel = $this->channel([
            $this->err(0, 'curl error for https://api.telegram.org/bot999:SUPERSECRET/sendMessage'),
        ]);

        $channel->notify('S', 'B');

        $this->assertStringNotContainsString('SUPERSECRET', $channel->lastError());
    }
}
