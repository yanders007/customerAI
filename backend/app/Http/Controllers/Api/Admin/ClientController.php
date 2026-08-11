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
        \Log::info('=== Début création client ===', ['request' => $request->all()]);
        
        try {
            $data = $request->validate([
                'name'  => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'unique:clients,email'],
            ]);
            
            \Log::info('Validation OK', $data);

            // ── Génération identifiants ───
            $plainPassword = $this->generateProPassword();
            $identifier    = 'CLIENT-' . strtoupper(Str::random(6));
            
            \Log::info('Identifiants générés', [
                'identifier' => $identifier,
                'password_length' => strlen($plainPassword)
            ]);

            // ── Création client ───
            $client = Client::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'client_identifier' => $identifier,
                'password'          => Hash::make($plainPassword),
                'is_active'         => false, // Par défaut inactif
            ]);

            \Log::info('Client créé en DB', ['client_id' => $client->id]);

            // ── Envoi email ───
            $emailStatus = 'Email non configuré';
            try {
                $loginUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/login-client';
                \Log::info('Tentative envoi email', ['to' => $data['email'], 'url' => $loginUrl]);
                
                Mail::to($data['email'])->send(new ClientCredentialsMail(
                    clientName: $data['name'],
                    identifier: $identifier,
                    password:   $plainPassword,
                    loginUrl:   $loginUrl,
                ));
                
                $emailStatus = 'Email envoyé avec succès';
                \Log::info('Email envoyé avec succès');
            } catch (\Exception $mailError) {
                \Log::warning('Échec envoi email', [
                    'email' => $data['email'],
                    'error' => $mailError->getMessage(),
                    'trace' => $mailError->getTraceAsString()
                ]);
                $emailStatus = 'Client créé mais email non envoyé (config SMTP manquante)';
            }

            return response()->json([
                'success' => true,
                'message' => $emailStatus,
                'data'    => [
                    'id'                => $client->id,
                    'name'              => $client->name,
                    'email'             => $client->email,
                    'client_identifier' => $identifier,
                    'password'          => $plainPassword,
                ],
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Erreur validation', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'error' => 'Validation échouée',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur création client', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erreur serveur : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère un mot de passe professionnel de type "Word@Word42"
     * Plus mémorisable qu'une suite aléatoire tout en restant sécurisé
     */
    private function generateProPassword(): string
    {
        $words = [
            'Secure', 'Digital', 'Network', 'System', 'Cloud', 'Tech',
            'Smart', 'Access', 'Portal', 'Connect', 'Prime', 'Elite',
            'Global', 'Master', 'Super', 'Ultra', 'Rapid', 'Swift',
            'Power', 'Strong', 'Safe', 'Shield', 'Guard', 'Vault',
        ];
        
        $special = ['@', '!', '#', '$', '%', '&', '*'];
        
        $word1 = $words[array_rand($words)];
        $word2 = $words[array_rand($words)];
        $symbol = $special[array_rand($special)];
        $number = rand(10, 99);
        
        // Format: Word1@Word2[99]
        return $word1 . $symbol . $word2 . $number;
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
