<?php

return [
    /*
    | Tambahkan 'login' ke dalam paths agar diizinkan
    */
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Lebih aman dan stabil sebutkan langsung alamat frontendmu
    'allowed_origins' => ['http://localhost:5173'], 

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Set ke true jika kamu butuh kirim cookie/session (biasanya iya untuk login)
    'supports_credentials' => true,
];