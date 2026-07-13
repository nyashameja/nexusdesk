<?php

declare(strict_types=1);

namespace App\Controllers\Web\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Mail\MailerInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Validation\Validator;

/**
 * Forgot / reset password. Tokens are single-use, hashed at rest, and expire
 * after 60 minutes. The "forgot" response is intentionally uniform to avoid
 * leaking which emails are registered.
 */
final class PasswordController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Database $db,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot', ['title' => 'Forgot password'], 'layouts/auth');
    }

    public function sendLink(Request $request): Response
    {
        $validator = Validator::make($request->only(['email']), ['email' => 'required|email']);
        if ($validator->fails()) {
            return (new RedirectResponse('/forgot-password'))->withErrors($validator->errors());
        }

        $email = (string) $request->input('email');
        $user = $this->users->findByEmail($email);
        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $this->db->run(
                'INSERT INTO password_resets (email, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))',
                [$email, hash('sha256', $token)]
            );
            $url = rtrim((string) config('app.url'), '/') . '/reset-password/' . $token . '?email=' . urlencode($email);
            $this->mailer->send(
                $email,
                $user->fullName(),
                'Reset your ' . setting('general.app_name', 'NexusDesk') . ' password',
                '<p>We received a request to reset your password.</p>'
                . '<p><a href="' . e($url) . '">Reset your password</a> (valid for 60 minutes).</p>'
                . '<p>If you did not request this, you can ignore this email.</p>'
            );
        }

        return (new RedirectResponse('/forgot-password'))
            ->with('status', 'If that email is registered, a reset link is on its way.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return $this->view('auth.reset', [
            'title' => 'Reset password',
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ], 'layouts/auth');
    }

    public function reset(Request $request): Response
    {
        $validator = Validator::make(
            $request->only(['email', 'token', 'password', 'password_confirmation']),
            [
                'email'    => 'required|email',
                'token'    => 'required',
                'password' => 'required|min:8|confirmed',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/reset-password/' . (string) $request->input('token')
                . '?email=' . urlencode((string) $request->input('email'))))
                ->withErrors($validator->errors());
        }

        $email = (string) $request->input('email');
        $row = $this->db->selectOne(
            'SELECT * FROM password_resets
             WHERE email = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [$email, hash('sha256', (string) $request->input('token'))]
        );

        if ($row === null) {
            return (new RedirectResponse('/forgot-password'))
                ->withErrors(['email' => ['This reset link is invalid or has expired.']]);
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null) {
            $this->users->updatePassword(
                $user->id,
                password_hash((string) $request->input('password'), PASSWORD_DEFAULT, ['cost' => 12])
            );
            $this->db->run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);
        }

        return (new RedirectResponse('/login'))->with('status', 'Your password has been reset. Please sign in.');
    }
}
