<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientCredentialsMail;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('name')->get()->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'client_identifier' => $client->client_identifier,
                'last_login' => $client->last_login?->toIso8601String(),
                'is_active' => $client->is_active,
                'projets_count' => $client->projets()->count(),
            ];
        });

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:clients,email'],
        ]);

        // ── Mot de passe généré automatiquement ─────────────────
        // L'admin ne saisit plus le mot de passe : on le génère nous-
        // mêmes côté serveur et on l'envoie uniquement par email au
        // client. Ça évite les mots de passe faibles/devinables tapés
        // à la va-vite, et retire une étape manuelle à l'admin.
        $plainPassword = Str::password(12);
        $identifier    = 'CLIENT-' . strtoupper(Str::random(6));

        $client = Client::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'client_identifier' => $identifier,
            'password'          => Hash::make($plainPassword),
        ]);

        // ── Envoi des identifiants par email ────────────────────
        $loginUrl = config('services.support.frontend_url') . '/login-client';
        Mail::to($data['email'])->send(new ClientCredentialsMail(
            clientName: $data['name'],
            identifier: $identifier,
            password:   $plainPassword,
            loginUrl:   $loginUrl,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Client créé et identifiants envoyés par email.',
            'data'    => [
                'id'                => $client->id,
                'name'              => $client->name,
                'email'             => $client->email,
                'client_identifier' => $identifier,
            ],
        ], 201);
    }

    public function update(Request $request)
    {
        $data   = $request->validate([
            'id'    => ['required', 'integer'],
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
        ]);
        $client = Client::findOrFail($data['id']);
        $client->update(['name' => $data['name'], 'email' => $data['email']]);
        return response()->json(['success' => true, 'message' => 'Client mis à jour.']);
    }

    public function destroy(Request $request)
    {
        $id = (int) $request->input('id');
        Client::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Client supprimé.']);
    }
}
