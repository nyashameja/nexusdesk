<?php

declare(strict_types=1);

namespace ParagonHostOps\Services;

use ParagonHostOps\Core\Session;
use ParagonHostOps\Repositories\LoginAttemptRepository;
use ParagonHostOps\Repositories\UserRepository;

/**
 * Authentication and role-based access control service.
 *
 * Uses password_hash()/password_verify(), regenerates the session on login to
 * prevent fixation, and enforces throttling + lockout via the login-attempt
 * repository. Permissions are cached in the session for the request lifetime.
 */
final class Auth
{
    private const RESULT_OK           = 'ok';
    public const RESULT_INVALID       = 'invalid';
    public const RESULT_LOCKED        = 'locked';
    public const RESULT_THROTTLED     = 'throttled';
    public const RESULT_INACTIVE      = 'inactive';

    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    public function __construct(
        private Session $session,
        private UserRepository $users,
        private LoginAttemptRepository $attempts,
        private int $maxAttempts,
        private int $lockoutMinutes,
    ) {
    }

    /**
     * Attempt a login.
     *
     * @return array{status:string, user:array<string,mixed>|null}
     */
    public function attempt(string $email, string $password, string $ip, string $userAgent): array
    {
        // Throttle by recent failures for this email/IP pair.
        if ($this->attempts->recentFailures($email, $ip, $this->lockoutMinutes) >= $this->maxAttempts) {
            return ['status' => self::RESULT_THROTTLED, 'user' => null];
        }

        $user = $this->users->findByEmail($email);

        // Always run password_verify against something to reduce timing leaks.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforcompatibility1234567890abcdefghi';
        $valid = password_verify($password, (string) $hash);

        if ($user === null || !$valid) {
            $this->attempts->record($email, $ip, false, $userAgent);
            if ($user !== null && ($this->attempts->recentFailures($email, $ip, $this->lockoutMinutes) + 1) >= $this->maxAttempts) {
                $this->attempts->lockUser((int) $user['id'], $this->lockoutMinutes);
            }
            return ['status' => self::RESULT_INVALID, 'user' => null];
        }

        if ($this->users->isLocked($user)) {
            $this->attempts->record($email, $ip, false, $userAgent);
            return ['status' => self::RESULT_LOCKED, 'user' => null];
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            $this->attempts->record($email, $ip, false, $userAgent);
            return ['status' => self::RESULT_INACTIVE, 'user' => null];
        }

        // Success: rotate the session, store identity, clear failures.
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->session->set('permissions', $this->users->permissionsFor((int) $user['id']));
        $this->session->set('roles', $this->users->rolesFor((int) $user['id']));

        $this->attempts->clearFailures($email, $ip);
        $this->users->updateLastLogin((int) $user['id'], $ip);
        $this->attempts->record($email, $ip, true, $userAgent);

        $this->cachedUser = $user;

        return ['status' => self::RESULT_OK, 'user' => $user];
    }

    public function logout(): void
    {
        $this->cachedUser = null;
        $this->session->forget('user_id');
        $this->session->forget('permissions');
        $this->session->forget('roles');
        $this->session->regenerate();
    }

    public function check(): bool
    {
        return $this->session->has('user_id');
    }

    public function id(): ?int
    {
        $id = $this->session->get('user_id');
        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        return $this->cachedUser = $this->users->findById((int) $this->id());
    }

    public function name(): string
    {
        return (string) ($this->user()['name'] ?? 'User');
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        /** @var array<int, string> $perms */
        $perms = $this->session->get('permissions', []);
        return is_array($perms) ? $perms : [];
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        /** @var array<int, string> $roles */
        $roles = $this->session->get('roles', []);
        return is_array($roles) ? $roles : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * Super administrators implicitly hold every permission.
     */
    public function can(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return in_array($permission, $this->permissions(), true);
    }
}
