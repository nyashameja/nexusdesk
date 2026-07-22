<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Validators\Validator;

/**
 * Handles login and logout. There is deliberately NO public registration.
 */
final class AuthController extends Controller
{
    public function __construct(
        private Auth $auth,
        private AuditLogger $audit,
    ) {
    }

    public function showLogin(Request $request, array $params): Response
    {
        $expired = $this->session()->has('_expired');
        $this->session()->forget('_expired');

        return $this->view('auth.login', [
            'error'   => $this->session()->getFlash('login_error'),
            'expired' => $expired,
        ]);
    }

    public function login(Request $request, array $params): Response
    {
        $email    = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $validator = (new Validator($request->all()))
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password');

        if ($validator->fails()) {
            $this->session()->flash('login_error', $validator->firstError());
            $this->flashOld($email);
            return $this->redirect('/login');
        }

        $result = $this->auth->attempt($email, $password, $request->ip(), $request->userAgent());

        if ($result['status'] === 'ok') {
            $this->audit->record('user.login', 'User logged in successfully.');
            return $this->redirect('/dashboard');
        }

        $this->session()->flash('login_error', $this->messageFor($result['status']));
        $this->flashOld($email);

        // A failed attempt for a known email is recorded inside Auth::attempt.
        if ($result['status'] === Auth::RESULT_INVALID) {
            $this->audit->record('user.login_failed', 'Failed login attempt for ' . $email);
        }

        return $this->redirect('/login');
    }

    public function logout(Request $request, array $params): Response
    {
        $this->audit->record('user.logout', 'User logged out.');
        $this->auth->logout();
        return $this->redirect('/login');
    }

    private function flashOld(string $email): void
    {
        $_SESSION['_old'] = ['email' => $email];
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            Auth::RESULT_THROTTLED => 'Too many failed attempts. Please wait a few minutes and try again.',
            Auth::RESULT_LOCKED    => 'This account is temporarily locked. Please try again later.',
            Auth::RESULT_INACTIVE  => 'This account is inactive. Contact an administrator.',
            default                => 'The email or password is incorrect.',
        };
    }
}
