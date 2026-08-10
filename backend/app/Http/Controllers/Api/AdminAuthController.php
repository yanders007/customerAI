<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    // Remplace admin/login.php
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $admin = Admin::where('email', $data['email'])->first();

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            return response()->json(['success' => false, 'error' => 'Identifiants invalides'], 401);
        }

        // Anti-fixation de session, équivalent à session_regenerate_id(true)
        $request->session()->regenerate();
        $request->session()->put('admin_id', $admin->id);

        return response()->json([
            'success' => true,
            'admin' => $admin, // password déjà caché via $hidden sur le Model
        ]);
    }

    // Remplace admin/logout.php
    public function logout(Request $request)
    {
        $request->session()->forget('admin_id');
        $request->session()->regenerate();

        return response()->json(['success' => true, 'message' => 'Déconnexion réussie']);
    }

    // Remplace admin/me.php
    public function me(Request $request)
    {
        $admin = Admin::find($request->session()->get('admin_id'));

        if (!$admin) {
            return response()->json(['success' => false, 'error' => 'Non autorisé'], 401);
        }

        return response()->json(['success' => true, 'admin' => $admin]);
    }
}
