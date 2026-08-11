<?php

use App\Http\Controllers\Api\Admin\AiConfigController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DocsController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AskController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProjectController;
use Illuminate\Support\Facades\Route;

// ── Auth admin ───────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    // Rate limiting strict sur login (5 tentatives/minute)
    Route::middleware(['throttle:5,1'])->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login']);
    });
    
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me',      [AdminAuthController::class, 'me']);

    Route::middleware('auth.admin')->group(function () {
        // Dashboard
        Route::get('/dashboard',         [DashboardController::class, 'index']);
        Route::get('/dashboard/tokens',   [DashboardController::class, 'tokens']);
        Route::get('/dashboard/{id}',     [DashboardController::class, 'client']);

        // Clients
        Route::get('/clients',    [ClientController::class, 'index']);
        Route::post('/clients',   [ClientController::class, 'store']);
        Route::put('/clients',    [ClientController::class, 'update']);
        Route::delete('/clients', [ClientController::class, 'destroy']);

        // Projets / Docs / FAQ
        Route::get('/docs',    [DocsController::class, 'index']);
        Route::get('/docs/{id}', [DocsController::class, 'show']);
        Route::post('/docs',   [DocsController::class, 'store']);
        Route::put('/docs',    [DocsController::class, 'update']);
        Route::delete('/docs', [DocsController::class, 'destroy']);

        // Conversations (liste pour l'admin)
        Route::get('/conversations', [AskController::class, 'adminList']);
        Route::delete('/conversations/{id}', [AskController::class, 'adminDeleteConversation']);

        // Configuration IA
        Route::get('/ai-config',                [AiConfigController::class, 'index']);
        Route::get('/ai-config/active',         [AiConfigController::class, 'show']);
        Route::post('/ai-config',               [AiConfigController::class, 'store']);
        Route::post('/ai-config/activate/{id}', [AiConfigController::class, 'activate']);
        Route::post('/ai-config/test',          [AiConfigController::class, 'test']);
        Route::delete('/ai-config/{id}',        [AiConfigController::class, 'destroy']);
    });
});

// ── Auth client ──────────────────────────────────────────────
Route::prefix('client')->group(function () {
    // Rate limiting strict sur login (5 tentatives/minute)
    Route::middleware(['throttle:5,1'])->group(function () {
        Route::post('/login', [ClientAuthController::class, 'login']);
    });
    
    Route::post('/logout', [ClientAuthController::class, 'logout']);
    Route::get('/me',      [ClientAuthController::class, 'me']);

    Route::middleware('auth.client')->group(function () {
        // Heartbeat pour statut en ligne (120 req/min = 1 toutes les 30s par client)
        Route::middleware(['throttle:120,1'])->group(function () {
            Route::post('/heartbeat', [ClientAuthController::class, 'heartbeat']);
        });

        // Projets (limité à 30 req/min)
        Route::middleware(['throttle:30,1'])->group(function () {
            Route::get('/projets',         [ProjectController::class, 'index']);
            Route::post('/select-project', [ProjectController::class, 'select']);
        });

        // Chat - Rate limiting modéré (60 messages/minute)
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::post('/ask', [AskController::class, '__invoke']);
        });
        
        // Conversations (30 req/min)
        Route::middleware(['throttle:30,1'])->group(function () {
            Route::get('/conversations',           [AskController::class, 'clientList']);
            Route::post('/conversations',          [AskController::class, 'createConversation']);
            Route::get('/conversations/{id}',      [AskController::class, 'showConversation']);
            Route::delete('/conversations/{id}',   [AskController::class, 'deleteConversation']);
        });
    });
});

// ── Support humain (lien email, sans auth session) ───────────
Route::prefix('support')->group(function () {
    Route::get('/conversation/{uuid}',         [AskController::class, 'show']);
    Route::post('/conversation/{uuid}/reply',  [AskController::class, 'humanReply']);
});

// ── Reset mot de passe (3 tentatives/minute pour éviter spam)
Route::prefix('password')->middleware(['throttle:3,1'])->group(function () {
    Route::post('/request', [PasswordResetController::class, 'request']);
    Route::post('/reset',   [PasswordResetController::class, 'reset']);
});
