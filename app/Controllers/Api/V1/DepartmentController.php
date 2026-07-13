<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Repositories\Contracts\DepartmentRepositoryInterface;

final class DepartmentController
{
    public function __construct(private readonly DepartmentRepositoryInterface $departments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = array_map(static fn (array $d): array => [
            'id'    => (int) $d['id'],
            'name'  => $d['name'],
            'email' => $d['email'] ?? null,
        ], $this->departments->all(publicOnly: true));

        return JsonResponse::ok($rows);
    }
}
