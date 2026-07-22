<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

/**
 * Development mock transport.
 *
 * Reads canned JSON fixtures from tests/fixtures/whm keyed by the WHM function
 * name in the request query. Enables full local development without a live
 * WHM server. NEVER used when WHM_MOCK_MODE is false (production).
 */
final class MockTransport implements WhmTransportInterface
{
    public function __construct(private string $fixtureDir)
    {
    }

    public function get(string $url, array $headers, array $query): array
    {
        // The WHM function name is the last path segment of the API URL.
        $function = basename(parse_url($url, PHP_URL_PATH) ?: '');
        $file     = rtrim($this->fixtureDir, '/') . '/' . $function . '.json';

        if (!is_file($file)) {
            return [
                'status'        => 404,
                'body'          => '',
                'error'         => "No mock fixture for '{$function}'.",
                'timedOut'      => false,
                'connectFailed' => false,
            ];
        }

        return [
            'status'        => 200,
            'body'          => (string) file_get_contents($file),
            'error'         => null,
            'timedOut'      => false,
            'connectFailed' => false,
        ];
    }
}
