<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

/**
 * cURL-based HTTP transport for WHM API calls.
 *
 * Enforces HTTPS, SSL verification (by default), and both connection and
 * request timeouts. The browser never talks to WHM — only this backend does.
 */
final class CurlTransport implements WhmTransportInterface
{
    public function __construct(
        private int $timeout,
        private int $connectTimeout,
        private bool $verifySsl,
    ) {
    }

    public function get(string $url, array $headers, array $query): array
    {
        $fullUrl = $url;
        if ($query !== []) {
            $fullUrl .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $ch = curl_init();

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);

        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $error  = $errno !== 0 ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'status'        => $status,
            'body'          => is_string($body) ? $body : '',
            'error'         => $error,
            'timedOut'      => in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED], true),
            'connectFailed' => in_array($errno, [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true),
        ];
    }
}
