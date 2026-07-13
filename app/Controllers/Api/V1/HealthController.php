<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;

final class HealthController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $db = false;
        try {
            $this->db->scalar('SELECT 1');
            $db = true;
        } catch (\Throwable) {
            $db = false;
        }

        return new JsonResponse([
            'status'  => $db ? 'ok' : 'degraded',
            'version' => 'v1',
            'time'    => date('c'),
            'checks'  => ['database' => $db],
        ], $db ? 200 : 503);
    }
}
