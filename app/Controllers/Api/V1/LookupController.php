<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;

/**
 * Lookups for building forms against the API: statuses, priorities, departments.
 */
final class LookupController
{
    public function __construct(
        private readonly LookupRepositoryInterface $lookups,
        private readonly DepartmentRepositoryInterface $departments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $simplify = static fn (array $rows, array $keys): array => array_map(
            static fn (array $r): array => array_intersect_key($r, array_flip($keys)),
            $rows
        );

        return JsonResponse::ok([
            'statuses'    => $simplify($this->lookups->statuses(), ['slug', 'name', 'colour']),
            'priorities'  => $simplify($this->lookups->priorities(), ['slug', 'name', 'colour']),
            'departments' => $simplify($this->departments->all(publicOnly: true), ['id', 'name']),
        ]);
    }
}
