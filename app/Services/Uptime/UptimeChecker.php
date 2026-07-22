<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Uptime;

/**
 * Performs a single uptime probe with PHP cURL and classifies the result.
 *
 * The classification logic is a pure static method so it can be unit-tested
 * without any network access; only probe() touches the network.
 */
final class UptimeChecker
{
    public const ONLINE      = 'online';
    public const SLOW        = 'slow';
    public const OFFLINE     = 'offline';
    public const SSL_PROBLEM = 'ssl_problem';

    public function __construct(
        private int $timeout = 15,
        private int $connectTimeout = 8,
        private int $slowThresholdMs = 2000,
    ) {
    }

    /**
     * @return array{status:string, status_code:?int, response_ms:int, is_up:bool, error:?string}
     */
    public function probe(string $url, int $expectedStatus): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'ParagonHostOps-UptimeBot/1.0',
        ]);

        $start = microtime(true);
        $body  = curl_exec($ch);
        $ms    = (int) round((microtime(true) - $start) * 1000);

        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : null;
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $sslError = in_array($errno, [
            CURLE_SSL_CONNECT_ERROR,
            CURLE_SSL_CERTPROBLEM,
            CURLE_SSL_CACERT,
            CURLE_PEER_FAILED_VERIFICATION,
        ], true);

        $status = self::classify($code, $expectedStatus, $ms, $error, $sslError, $this->slowThresholdMs);

        return [
            'status'      => $status,
            'status_code' => $code > 0 ? $code : null,
            'response_ms' => $ms,
            'is_up'       => $status === self::ONLINE || $status === self::SLOW,
            'error'       => $error,
        ];
    }

    /**
     * Pure classification of a probe result.
     */
    public static function classify(
        int $code,
        int $expected,
        int $responseMs,
        ?string $error,
        bool $sslError,
        int $slowThresholdMs,
    ): string {
        if ($sslError) {
            return self::SSL_PROBLEM;
        }
        if ($error !== null || $code === 0) {
            return self::OFFLINE;
        }
        // A non-matching status code (and non-2xx) is treated as down.
        if ($code !== $expected && !($code >= 200 && $code < 400)) {
            return self::OFFLINE;
        }
        if ($responseMs > $slowThresholdMs) {
            return self::SLOW;
        }
        return self::ONLINE;
    }
}
