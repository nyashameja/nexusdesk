<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Minimal cURL-based HTTP client for external API calls (Zoho, AI). Verifies
 * TLS against the system CA bundle. Returns a simple response struct; never
 * throws on HTTP errors (the caller inspects the status).
 */
final class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|string|null $body
     * @return array{status:int,body:string,error:?string}
     */
    public function request(string $method, string $url, array $headers = [], array|string|null $body = null): array
    {
        $ch = curl_init();
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headerLines,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => is_string($responseBody) ? $responseBody : '',
            'error'  => $error,
        ];
    }

    /** @param array<string,string> $headers @return array<string,mixed>|null */
    public function getJson(string $url, array $headers = []): ?array
    {
        $headers['Accept'] = 'application/json';
        $response = $this->request('GET', $url, $headers);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return null;
        }
        $decoded = json_decode($response['body'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
