<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use App\Infrastructure\Logging\LoggerInterface;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Support\Security\Session;

/**
 * Authentication use-cases: credential verification with brute-force
 * throttling, session establishment (with fixation regeneration), logout, and
 * loading the current user from the session for each request.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Database $db,
        private readonly AuditService $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{ok:bool,user?:User,error?:string}
     */
    public function attempt(string $email, string $password, string $ip, string $userAgent): array
    {
        if ($this->tooManyAttempts($email, $ip)) {
            return ['ok' => false, 'error' => 'Too many attempts. Please try again later.'];
        }

        $user = $this->users->findByEmail($email);
        $valid = $user !== null && password_verify($password, $user->passwordHash);

        $this->recordAttempt($email, $ip, $userAgent, $valid && $user->isActive);

        if (!$valid) {
            return ['ok' => false, 'error' => 'These credentials do not match our records.'];
        }
        if (!$user->isActive) {
            return ['ok' => false, 'error' => 'This account is inactive. Contact an administrator.'];
        }

        // Rehash if the algorithm/cost changed.
        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT, ['cost' => 12])) {
            $this->users->updatePassword($user->id, password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]));
        }

        return ['ok' => true, 'user' => $user];
    }

    public function login(User $user, string $ip): void
    {
        Session::regenerate();
        Session::put('user_id', $user->id);
        Session::put('_started_at', time());
        Session::put('_last_seen', time());
        $this->users->recordLogin($user->id, $ip);
        $this->audit->log('user.login', 'User', $user->id, actorId: $user->id);
        AuthContext::set($user);
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        if ($userId !== null) {
            $this->audit->log('user.logout', 'User', (int) $userId, actorId: (int) $userId);
        }
        Session::invalidate();
        AuthContext::set(null);
    }

    /** Load the session user (called by AuthMiddleware). */
    public function userFromSession(): ?User
    {
        $userId = Session::get('user_id');
        if ($userId === null) {
            return null;
        }
        $user = $this->users->findById((int) $userId);
        if ($user === null || !$user->isActive) {
            Session::invalidate();
            return null;
        }
        return $user;
    }

    private function tooManyAttempts(string $email, string $ip): bool
    {
        $count = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0 AND (email = ? OR ip_address = ?)
               AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$email, @inet_pton($ip) ?: $ip]
        );
        return $count >= 5;
    }

    private function recordAttempt(string $email, string $ip, string $userAgent, bool $successful): void
    {
        $this->db->run(
            'INSERT INTO login_attempts (email, ip_address, successful, user_agent) VALUES (?, ?, ?, ?)',
            [$email, @inet_pton($ip) ?: null, $successful ? 1 : 0, substr($userAgent, 0, 255)]
        );
    }
}
