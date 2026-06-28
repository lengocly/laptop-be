<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Chỉ FE dev được gọi API — tránh site lạ dùng token/cookie
    'allowed_origins' => [
        'http://localhost:5173', // Frontend dev
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
        'http://10.1.0.96:5173',
        'http://10.1.0.96:8000',
    ],

    'allowed_origins_patterns' => [
        '#^http://10\.\d+\.\d+\.\d+(:\d+)?$#',
        '#^http://192\.168\.\d+\.\d+(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
