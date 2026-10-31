<?php

$extraOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$frontendUrl = trim((string) env('FRONTEND_URL', ''));
if ($frontendUrl !== '') {
    $extraOrigins[] = $frontendUrl;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([
        // Local development (Vite / Next / Three.js dev servers)
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5176',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:8000',
    ], $extraOrigins))),

    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(:\d+)?$/',
        '/^https?:\/\/127\.0\.0\.1(:\d+)?$/',
        '/^https?:\/\/.*\.vercel\.app$/',
        '/^https?:\/\/.*\.traefik\.me$/',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-User-Ai-Key',
        'X-User-Ai-Base-Url',
        'X-User-Tavily-Key',
    ],

    'exposed_headers' => ['Content-Disposition', 'Content-Length', 'Content-Type'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
