<?php

return [

    'paths' => [
        'api/*',
        'login',
        'logout',
        'sanctum/csrf-cookie'
    ],

    'allowed_methods' => ['*'],

    // Izinkan DEV + PROD
    'allowed_origins' => [
        'http://localhost:5173',
        'https://sportcenter.biz.id',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // WAJIB true kalau pakai session / sanctum / cookie
    'supports_credentials' => true,
];
