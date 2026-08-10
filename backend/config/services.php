<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', 'http://localhost:5678/webhook/assistant'),
    ],

    // Utilisé côté Laravel uniquement pour la vectorisation (RAG) des
    // documentations — l'appel qui génère la RÉPONSE au client passe
    // toujours par n8n / le nœud Gemini de votre workflow. Une clé API
    // Gemini gratuite suffit largement (Google AI Studio).
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'cohere' => [
        // Cohere : API d'embeddings utilisée par le RAG (segmentation +
        // recherche par similarité). 20 requêtes/minute, modèle
        // embed-multilingual-v3.0 (1024 dimensions). Clé gratuite sur
        // https://dashboard.cohere.com/api-keys
        'api_key' => env('COHERE_API_KEY'),
    ],

    'support' => [
        'email'        => env('SUPPORT_EMAIL', null),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

];
