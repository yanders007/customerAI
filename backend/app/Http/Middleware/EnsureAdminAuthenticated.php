<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminAuthenticated
{
    // Équivalent direct de requireAdmin() dans ton ancien auth.php
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('admin_id')) {
            return response()->json(['success' => false, 'error' => 'Non autorisé'], 401);
        }

        return $next($request);
    }
}
