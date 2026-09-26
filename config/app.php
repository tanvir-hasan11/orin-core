<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Orin',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'session_name' => getenv('APP_SESSION_NAME') ?: 'orin_session',
    'session_lifetime' => (int) (getenv('APP_SESSION_LIFETIME') ?: 7200),
    'signup_open' => filter_var(getenv('APP_SIGNUP_OPEN') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'default_plan_code' => getenv('APP_DEFAULT_PLAN') ?: 'free',
    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: 'log',
        'host' => getenv('MAIL_HOST') ?: '',
        'port' => (int) (getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'from' => getenv('MAIL_FROM') ?: 'no-reply@orin.local',
    ],
];
