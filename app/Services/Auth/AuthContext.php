<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

/**
 * Holds the authenticated user for the current request so views/helpers can
 * reach it without threading it through every call. Set by AuthMiddleware /
 * ApiAuthMiddleware after a successful identity check.
 */
final class AuthContext
{
    private static ?User $user = null;

    public static function set(?User $user): void
    {
        self::$user = $user;
    }

    public static function user(): ?User
    {
        return self::$user;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function id(): ?int
    {
        return self::$user?->id;
    }

    public static function can(string $permission): bool
    {
        return self::$user?->can($permission) ?? false;
    }
}
