<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Public;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Validator;
use Orin\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        if (current_user() !== null) {
            return $this->redirectByRole((string) current_user()['role']);
        }

        return Response::html(view('auth/login', ['title' => 'Log in - Orin']));
    }

    public function login(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $validator = new Validator(['email' => $email, 'password' => $password], [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$validator->validate()) {
            return Response::html(view('auth/login', [
                'title' => 'Log in - Orin',
                'errors' => $validator->errors(),
                'old' => ['email' => $email],
            ]), 422);
        }

        /** @var AuthService $auth */
        $auth = app('auth');
        $user = $auth->attempt($email, $password);

        if ($user === null) {
            return Response::html(view('auth/login', [
                'title' => 'Log in - Orin',
                'errors' => ['email' => ['Invalid credentials.']],
                'old' => ['email' => $email],
            ]), 422);
        }

        return $this->redirectByRole((string) $user['role']);
    }

    public function logout(Request $request): Response
    {
        /** @var AuthService $auth */
        $auth = app('auth');
        $auth->logout();

        return Response::redirect('/');
    }

    private function redirectByRole(string $role): Response
    {
        return match ($role) {
            'super_admin', 'admin' => Response::redirect('/admin/dashboard'),
            'merchant' => Response::redirect('/merchant/dashboard'),
            default => Response::redirect('/'),
        };
    }
}
