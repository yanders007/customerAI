<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // On force le démarrage de session sur TOUTES les routes API, sans
        // dépendre de la détection d'origine fragile de Sanctum (qui exigeait
        // que le port du frontend soit dans SANCTUM_STATEFUL_DOMAINS et
        // cassait silencieusement sinon). Ça reproduit fidèlement l'ancien
        // comportement PHP session_start() + cookie PHPSESSID.
        $middleware->group('api', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, // ✅ CSRF activé
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Alias utilisés dans routes/api.php, équivalents directs de
        // requireAdmin() / requireClient() dans ton ancien auth.php
        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\EnsureAdminAuthenticated::class,
            'auth.client' => \App\Http\Middleware\EnsureClientAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();