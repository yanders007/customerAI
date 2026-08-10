<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

class EnsureClientAuthenticated
{
    // Équivalent direct de requireClient() dans ton ancien auth.php
    public function handle(Request $request, Closure $next)
    {
        $clientId = $request->session()->get('client_id');
        
        if (!$clientId) {
            return response()->json(['success' => false, 'error' => 'Non autorisé'], 401);
        }

        // Mettre à jour last_login à chaque requête authentifiée (max 1x par heure)
        $client = Client::find($clientId);
        if ($client && (!$client->last_login || $client->last_login->lt(now()->subHour()))) {
            $client->update(['last_login' => now()]);
        }

        return $next($request);
    }
}
