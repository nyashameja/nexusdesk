<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\Auth\AuthContext;

final class MeController
{
    public function show(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        if ($user === null) {
            return JsonResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        return JsonResponse::ok([
            'id'          => $user->id,
            'name'        => $user->fullName(),
            'email'       => $user->email,
            'role'        => $user->roleSlug,
            'permissions' => $user->permissions,
        ]);
    }
}
