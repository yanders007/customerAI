<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // ⚠️ PRODUCTION : Remplacer par votre domaine uniquement
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Indispensable : autorise les cookies de session (PHPSESSID)
    'supports_credentials' => true,

];
