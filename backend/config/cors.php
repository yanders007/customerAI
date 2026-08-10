<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // ⚠️ PRODUCTION : Remplacer par votre domaine uniquement
    // Exemple : ['https://votre-domaine.com']
    // En développement, garder localhost
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'), // Utilise FRONTEND_URL depuis .env
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Indispensable : autorise les cookies de session (PHPSESSID)
    'supports_credentials' => true,

];