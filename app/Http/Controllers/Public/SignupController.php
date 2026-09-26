<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Public;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Validator;
use Orin\Services\AuthService;

final class SignupController
{
    public function show(Request $request): Response
    {
        $config = app('config');
        if (($config['app']['signup_open'] ?? true) === false) {
            return Response::html('<h1>Signups closed</h1>', 403);
        }

        return Response::html(view('auth/signup', ['title' => 'Sign up - Orin']));
    }

    public function store(Request $request): Response
    {
        $config = app('config');
        if (($config['app']['signup_open'] ?? true) === false) {
            return Response::html('<h1>Signups closed</h1>', 403);
        }

        $data = [
            'name' => trim((string) $request->input('name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'company' => trim((string) $request->input('company', '')),
            'password' => (string) $request->input('password', ''),
        ];

        $validator = new Validator($data + [
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ], [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'company' => 'required|min:2|max:180',
            'password' => 'required|min:8|same:password_confirmation',
        ]);

        if (!$validator->validate()) {
            return Response::html(view('auth/signup', [
                'title' => 'Sign up - Orin',
                'errors' => $validator->errors(),
                'old' => $data,
            ]), 422);
        }

        /** @var AuthService $auth */
        $auth = app('auth');

        $existing = app('db')->first('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $data['email']]);
        if ($existing !== null) {
            return Response::html(view('auth/signup', [
                'title' => 'Sign up - Orin',
                'errors' => ['email' => ['An account with this email already exists.']],
                'old' => $data,
            ]), 422);
        }

        $user = $auth->registerMerchant([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'],
            'password' => $data['password'],
        ]);

        $auth->startSession($user);

        return Response::redirect('/merchant/dashboard');
    }
}
