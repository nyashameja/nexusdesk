<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

use ParagonHostOps\Core\Logger;

/**
 * Telegram delivery via the Bot API (HTTPS, free, no approval process).
 *
 * Responsibilities are deliberately narrow: transport, retry policy and error
 * reporting. Message wording lives in TelegramMessageFormatter.
 *
 * Security notes:
 *  - The bot token appears only in the request URL. It is never logged, never
 *    returned in lastError(), and never rendered in the UI — redactUrl()
 *    strips it from anything that escapes this class.
 *  - Transient failures (network, 5xx, 429) are retried a bounded number of
 *    times; permanent ones (401/403/400 — bad token, blocked bot, bad chat id)
 *    fail immediately, because retrying a misconfiguration just wastes runs.
 */
final class TelegramAlertChannel implements AlertChannelInterface
{
    private const API_BASE    = 'https://api.telegram.org/bot';
    private const MAX_LENGTH  = 4000;   // Telegram hard limit is 4096.
    private const MAX_ATTEMPTS = 3;
    private const TIMEOUT      = 15;
    private const CONNECT_TIMEOUT = 8;

    /** HTTP statuses worth retrying — everything else is a config problem. */
    private const RETRYABLE = [408, 425, 429, 500, 502, 503, 504];

    /** @var callable(string,array):array{ok:bool,status:int,error:string} */
    private $transport;

    private string $lastError = '';

    /**
     * @param array{enabled?:bool, bot_token?:string, chat_id?:string} $config
     * @param callable(string,array):array{ok:bool,status:int,error:string}|null $transport
     */
    public function __construct(
        private array $config,
        ?callable $transport = null,
        private ?Logger $logger = null,
        private ?TelegramMessageFormatter $formatter = null,
        private ?int $sleepMicroseconds = null,
    ) {
        $this->transport = $transport ?? self::curlTransport();
        $this->formatter ??= new TelegramMessageFormatter();
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['enabled'])
            && !empty($this->config['bot_token'])
            && self::isValidChatId((string) ($this->config['chat_id'] ?? ''));
    }

    /**
     * Telegram chat ids are numeric (negative for groups/channels) or an
     * @channelusername. Validating up front turns a silent delivery failure
     * into a clear "not configured" state in Settings.
     */
    public static function isValidChatId(string $chatId): bool
    {
        $chatId = trim($chatId);
        if ($chatId === '') {
            return false;
        }

        return (bool) preg_match('/^-?\d+$/', $chatId)
            || (bool) preg_match('/^@[A-Za-z][A-Za-z0-9_]{4,}$/', $chatId);
    }

    /**
     * Plain text send (test alerts). Escaped, so a stray "<" cannot break
     * Telegram's HTML parser.
     */
    public function notify(string $subject, string $body): bool
    {
        $text = '<b>' . TelegramMessageFormatter::escape($subject) . '</b>'
              . "\n\n" . TelegramMessageFormatter::escape($body);

        return $this->send($text, null);
    }

    /**
     * Detected alerts, rendered as a single detailed message (one alert) or a
     * grouped digest (several), with an inline "Open in HostOps" button.
     *
     * @param array<int, Alert> $alerts
     */
    public function notifyAlerts(array $alerts, string $subject, string $plainBody): bool
    {
        if ($alerts === []) {
            return false;
        }

        $text = $this->formatter->digest(array_values($alerts));

        // A single alert links straight to its record; a digest links to the list.
        $keyboard = count($alerts) === 1
            ? $this->formatter->inlineKeyboard($alerts[0]->link)
            : $this->formatter->inlineKeyboard('/notifications', 'View all alerts');

        return $this->send($text, $keyboard);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $keyboard
     */
    private function send(string $text, ?array $keyboard): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Telegram is not configured (enable it and set a bot token and chat id).';
            return false;
        }

        $params = [
            'chat_id'                  => (string) $this->config['chat_id'],
            'text'                     => mb_substr($text, 0, self::MAX_LENGTH),
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => 'true',
        ];
        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_SLASHES) ?: '';
        }

        $url = self::API_BASE . $this->config['bot_token'] . '/sendMessage';

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $result = ($this->transport)($url, $params);
            $status = (int) $result['status'];

            if ($result['ok'] === true && $status >= 200 && $status < 300) {
                $this->lastError = '';
                return true;
            }

            // Redact here rather than trusting the transport: lastError() is
            // rendered in the UI, so this class must guarantee its own output.
            $error = self::redactUrl((string) ($result['error'] ?? ''));
            $this->lastError = trim(sprintf('HTTP %d%s', $status, $error !== '' ? ': ' . $error : ''));

            $retryable = $status === 0 || in_array($status, self::RETRYABLE, true);
            if (!$retryable || $attempt === self::MAX_ATTEMPTS) {
                $this->logger?->warning('Telegram alert delivery failed', [
                    'status'    => $status,
                    'error'     => $error,
                    'attempts'  => $attempt,
                    'retryable' => $retryable,
                ]);
                return false;
            }

            // Linear backoff; short enough to stay inside a cron run.
            usleep($this->sleepMicroseconds ?? ($attempt * 400_000));
        }

        return false;
    }

    /**
     * Remove any bot token that appears in a message before it is logged or
     * displayed. cURL error strings can echo the request URL back.
     */
    public static function redactUrl(string $message): string
    {
        return (string) preg_replace('#(/bot)[0-9]+:[A-Za-z0-9_-]+#', '$1[REDACTED]', $message);
    }

    /**
     * @return callable(string,array):array{ok:bool,status:int,error:string}
     */
    private static function curlTransport(): callable
    {
        return static function (string $url, array $params): array {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body      = curl_exec($ch);
            $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $decoded = is_string($body) ? json_decode($body, true) : null;
            $ok = is_array($decoded) && ($decoded['ok'] ?? false) === true;

            $error = $curlError !== ''
                ? $curlError
                : (is_array($decoded)
                    ? (string) ($decoded['description'] ?? '')
                    : (is_string($body) ? substr($body, 0, 300) : ''));

            return ['ok' => $ok, 'status' => $status, 'error' => self::redactUrl($error)];
        };
    }
}
