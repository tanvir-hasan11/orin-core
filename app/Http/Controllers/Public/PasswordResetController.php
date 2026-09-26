<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Public;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Validator;
use Orin\Services\AuthService;

final class PasswordResetController
{
    public function showForgot(Request $request): Response
    {
        return Response::html(view('auth/forgot', ['title' => 'Forgot password - Orin']));
    }

    public function send(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        $validator = new Validator(['email' => $email], ['email' => 'required|email']);
        if (!$validator->validate()) {
            return Response::html(view('auth/forgot', [
                'title' => 'Forgot password - Orin',
                'errors' => $validator->errors(),
                'old' => ['email' => $email],
            ]), 422);
        }

        /** @var AuthService $auth */
        $auth = app('auth');
        $token = $auth->requestPasswordReset($email);

        $debugLink = null;
        if ($token !== null) {
            $base = (string) (app('config')['app']['url'] ?? '');
            $debugLink = rtrim($base, '/') . '/reset-password?email=' . urlencode($email) . '&token=' . urlencode($token);

            app('logger')->info('Password reset requested', ['email' => $email, 'link' => $debugLink]);
        }

        return Response::html(view('auth/forgot', [
            'title' => 'Forgot password - Orin',
            'sent' => true,
            'debugLink' => $debugLink,
        ]));
    }

    public function showReset(Request $request): Response
    {
        return Response::html(view('auth/reset', [
            'title' => 'Reset password - Orin',
            'email' => (string) $request->query['email'] ?? '',
            'token' => (string) $request->query['token'] ?? '',
        ]));
    }

    public function reset(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');

        $validator = new Validator([
            'email' => $email,
            'password' => $password,
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ], [
            'email' => 'required|email',
            'password' => 'required|min:8|same:password_confirmation',
        ]);

        if (!$validator->validate() || $token === '') {
            return Response::html(view('auth/reset', [
                'title' => 'Reset password - Orin',
                'errors' => $validator->validate() ? ['token' => ['Invalid reset link.']] : $validator->errors(),
                'email' => $email,
                'token' => $token,
            ]), 422);
        }

        /** @var AuthService $auth */
        $auth = app('auth');

        if (!$auth->resetPassword($email, $token, $password)) {
            return Response::html(view('auth/reset', [
                'title' => 'Reset password - Orin',
                'errors' => ['token' => ['This reset link is invalid or has expired.']],
                'email' => $email,
                'token' => $token,
            ]), 422);
        }

        return Response::redirect('/login');
    }
}
