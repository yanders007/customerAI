<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Limite les questions par CLIENT (pas par IP) : chaque client authentifié
        // a droit à 20 questions / heure. Protège le quota du LLM gratuit contre
        // un seul client qui accapare tout le service.
        RateLimiter::for('ask-limit', function (Request $request) {
            $clientId = $request->session()->get('client_id');

            return Limit::perHour(20)
                ->by('ask:' . $clientId)
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'error' => 'Vous avez atteint la limite de 20 questions par heure. Réessayez plus tard.',
                    ], 429);
                });
        });
    }
}