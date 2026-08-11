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
        error_log('=== DEBUT CREATION CLIENT ===');
        error_log('Request: ' . json_encode($request->all()));
        
        try {
            $data = $request->validate([
                'name'  => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'unique:clients,email'],
            ]);
            
            error_log('✓ Validation OK: ' . json_encode($data));

            // ── Génération identifiants ───
            $plainPassword = $this->generateProPassword();
            $identifier    = 'CLIENT-' . strtoupper(Str::random(6));
            
            error_log('✓ Identifiants générés: ' . $identifier);

            // ── Création client ───
            $client = Client::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'client_identifier' => $identifier,
                'password'          => Hash::make($plainPassword),
                'is_active'         => false,
            ]);

            error_log('✓ Client créé en DB avec ID: ' . $client->id);

            // ── Envoi email ───
            $emailStatus = 'Email non configuré';
            try {
                $loginUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/login-client';
                error_log('→ Tentative envoi email à: ' . $data['email']);
                error_log('→ Login URL: ' . $loginUrl);
                error_log('→ MAIL_HOST: ' . env('MAIL_HOST'));
                error_log('→ MAIL_FROM: ' . env('MAIL_FROM_ADDRESS'));
                
                Mail::to($data['email'])->send(new ClientCredentialsMail(
                    clientName: $data['name'],
                    identifier: $identifier,
                    password:   $plainPassword,
                    loginUrl:   $loginUrl,
                ));
                
                $emailStatus = 'Email envoyé avec succès';
                error_log('✓✓✓ EMAIL ENVOYÉ AVEC SUCCÈS ✓✓✓');
            } catch (\Exception $mailError) {
                error_log('✗✗✗ ÉCHEC ENVOI EMAIL ✗✗✗');
                error_log('Erreur: ' . $mailError->getMessage());
                error_log('Fichier: ' . $mailError->getFile() . ':' . $mailError->getLine());
                $emailStatus = 'Client créé mais email non envoyé';
            }

            error_log('=== FIN CREATION CLIENT ===');

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
            error_log('✗ Erreur validation: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Validation échouée',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            error_log('✗✗✗ ERREUR CRITIQUE ✗✗✗');
            error_log('Message: ' . $e->getMessage());
            error_log('Fichier: ' . $e->getFile() . ':' . $e->getLine());
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
