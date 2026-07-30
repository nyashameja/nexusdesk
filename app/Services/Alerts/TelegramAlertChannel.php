<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * Telegram delivery channel via the Bot API (HTTPS, no approval, free).
 *
 * The HTTP transport is injectable so tests never hit the network.
 */
final class TelegramAlertChannel implements AlertChannelInterface
{
    /** @var callable(string,array):array{ok:bool,status:int} */
    private $transport;

    /**
     * @param array{enabled:bool, bot_token:string, chat_id:string} $config
     * @param callable(string,array):array{ok:bool,status:int}|null   $transport
     */
    public function __construct(
        private array $config,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? self::curlTransport();
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['enabled'])
            && !empty($this->config['bot_token'])
            && !empty($this->config['chat_id']);
    }

    public function notify(string $subject, string $body): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        // Telegram messages are capped at 4096 characters.
        $text = mb_substr($subject . "\n\n" . $body, 0, 4000);

        $url = 'https://api.telegram.org/bot' . $this->config['bot_token'] . '/sendMessage';
        $result = ($this->transport)($url, [
            'chat_id'                  => $this->config['chat_id'],
            'text'                     => $text,
            'disable_web_page_preview' => true,
        ]);

        return $result['ok'] === true && $result['status'] >= 200 && $result['status'] < 300;
    }

    /**
     * @return callable(string,array):array{ok:bool,status:int}
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
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $decoded = is_string($body) ? json_decode($body, true) : null;
            $ok = is_array($decoded) && ($decoded['ok'] ?? false) === true;

            return ['ok' => $ok, 'status' => $status];
        };
    }
}
