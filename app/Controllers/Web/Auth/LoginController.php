<?php

declare(strict_types=1);

namespace App\Controllers\Web\Auth;

use App\Core\Controller;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthService;
use App\Support\Security\Session;
use App\Support\Validation\Validator;

final class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function show(Request $request): Response
    {
        return $this->view('auth.login', [
            'title' => 'Sign in',
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $validator = Validator::make($request->only(['email', 'password']), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->backToLogin($validator->errors(), $request);
        }

        $result = $this->auth->attempt(
            (string) $request->input('email'),
            (string) $request->input('password'),
            $request->ip(),
            $request->userAgent(),
        );

        if (!$result['ok']) {
            return $this->backToLogin(['email' => [$result['error']]], $request);
        }

        $this->auth->login($result['user'], $request->ip());

        $intended = Session::get('_intended');
        Session::forget('_intended');
        $target = is_string($intended) && $intended !== '/login' ? $intended : $result['user']->homePath();

        return new RedirectResponse($target);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return new RedirectResponse('/login');
    }

    /** @param array<string,string[]> $errors */
    private function backToLogin(array $errors, Request $request): RedirectResponse
    {
        return (new RedirectResponse('/login'))
            ->withErrors($errors)
            ->withInput(['email' => $request->input('email')]);
    }
}
