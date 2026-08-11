<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientAuthController extends Controller
{
    // Remplace client/login.php
    public function login(Request $request)
    {
        $data = $request->validate([
            'client_identifier' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $client = Client::where('client_identifier', $data['client_identifier'])->first();

        if (!$client || !Hash::check($data['password'], $client->password)) {
            return response()->json(['success' => false, 'error' => 'Identifiants invalides'], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('client_id', $client->id);
        $request->session()->forget('project_id'); // reset du projet sélectionné à chaque connexion

        // Mettre à jour last_login et last_seen
        $client->update([
            'last_login' => now(),
            'last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'client' => $client,
        ]);
    }

    // Remplace client/logout.php
    public function logout(Request $request)
    {
        $request->session()->forget(['client_id', 'project_id']);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'message' => 'Déconnexion réussie']);
    }

    // Remplace client/me.php
    public function me(Request $request)
    {
        $client = Client::find($request->session()->get('client_id'));

        if (!$client) {
            return response()->json(['success' => false, 'error' => 'Non autorisé'], 401);
        }

        $projectId = $request->session()->get('project_id');
        $project = $projectId ? [
            'id'         => $projectId,
            'nom_projet' => $request->session()->get('project_nom'),
        ] : null;

        return response()->json([
            'success' => true,
            'client' => $client,
            'project' => $project,
        ]);
    }

    // Heartbeat : met à jour last_seen pour indiquer que le client est actif
    public function heartbeat(Request $request)
    {
        $clientId = $request->session()->get('client_id');
        
        if (!$clientId) {
            return response()->json(['success' => false, 'error' => 'Non autorisé'], 401);
        }

        $client = Client::find($clientId);
        if ($client) {
            $client->update(['last_seen' => now()]);
        }

        return response()->json(['success' => true]);
    }
}