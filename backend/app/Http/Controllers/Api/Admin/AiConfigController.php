<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
use App\Models\AiUsage;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConfigController extends Controller
{
    public function __construct(private AiService $aiService) {}

    // ── GET /admin/ai-config ──────────────────────────────────────────────
    // Renvoie toutes les configs (pour permettre de basculer entre plusieurs)
    public function index(): JsonResponse
    {
        $configs = AiConfig::orderBy('is_active', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'label'         => $c->label,
                'provider'      => $c->provider,
                'model'         => $c->model,
                'is_active'     => $c->is_active,
                'api_key_hint'  => $this->maskKey($c->api_key),
                'has_key'       => !empty($c->api_key),
                'last_used_at'  => $c->last_used_at,
                'updated_at'    => $c->updated_at,
            ]);

        return response()->json(['success' => true, 'data' => $configs]);
    }

    // ── GET /admin/ai-config/active ───────────────────────────────────────
    // Renvoie uniquement la config active
    public function show(): JsonResponse
    {
        $config = AiConfig::where('is_active', true)->latest()->first();

        if (!$config) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $apiKey = $config->api_key;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $config->id,
                'label'         => $config->label,
                'provider'      => $config->provider,
                'model'         => $config->model,
                'is_active'     => $config->is_active,
                'system_prompt' => $config->system_prompt,
                'api_key_hint'  => $this->maskKey($apiKey ?? ''),
                'has_key'       => !empty($apiKey) && strlen($apiKey) >= 10,
                'key_length'    => strlen($apiKey ?? ''),
                'last_used_at'  => $config->last_used_at,
                'updated_at'    => $config->updated_at,
            ],
        ]);
    }

    // ── POST /admin/ai-config/activate/{id} ───────────────────────────────
    // Activer une config existante
    public function activate(int $id): JsonResponse
    {
        $config = AiConfig::findOrFail($id);
        
        // Désactiver toutes les autres
        AiConfig::where('is_active', true)->update(['is_active' => false]);
        
        // Activer celle-ci
        $config->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => "Configuration {$config->provider} activée.",
        ]);
    }

    // ── POST /admin/ai-config ─────────────────────────────────────────────
    // Crée ou remplace la config active
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'      => ['required', 'string', 'in:openai,gemini,anthropic,mistral,groq,deepseek,together,openrouter,perplexity,xai,cohere-chat'],
            'model'         => ['required', 'string', 'max:100'],
            'api_key'       => ['required', 'string', 'min:10', 'max:500'],
            'label'         => ['nullable', 'string', 'max:120'],
            'system_prompt' => ['nullable', 'string', 'max:5000'],
        ]);

        // Désactiver toutes les configs précédentes
        AiConfig::where('is_active', true)->update(['is_active' => false]);

        // Créer une nouvelle config (le mutateur chiffrera automatiquement api_key)
        $config = new AiConfig([
            'provider'      => $data['provider'],
            'model'         => $data['model'],
            'label'         => $data['label'] ?? null,
            'is_active'     => true,
            'system_prompt' => $data['system_prompt'] ?? null,
        ]);
        
        // Le mutateur setApiKeyAttribute() va automatiquement chiffrer et stocker dans api_key_encrypted
        $config->api_key = $data['api_key'];
        $config->save();

        return response()->json([
            'success' => true,
            'message' => "Configuration {$data['provider']} ({$data['model']}) sauvegardée.",
            'data'    => ['id' => $config->id, 'provider' => $config->provider, 'model' => $config->model],
        ], 201);
    }

    // ── POST /admin/ai-config/test ────────────────────────────────────────
    // Teste la config fournie sans la sauvegarder (ou teste la config active)
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'model'    => ['required', 'string'],
            'api_key'  => ['required', 'string'],
        ]);

        // Config temporaire (non sauvegardée) pour le test
        $tempConfig = new AiConfig([
            'provider' => $data['provider'],
            'model'    => $data['model'],
        ]);
        $tempConfig->api_key = $data['api_key'];

        $result = $this->aiService->testConfig($tempConfig);

        AiUsage::recordUsage([
            'admin_id'         => $request->session()->get('admin_id'),
            'request_type'     => 'config_test',
            'provider'         => $data['provider'],
            'model'            => $data['model'],
            'ai_config_id'     => null,
            'tokens_input'     => (int) ($result['tokens_input'] ?? 0),
            'tokens_output'    => (int) ($result['tokens_output'] ?? 0),
            'embedding_tokens' => 0,
            'metadata'         => ['success' => empty($result['error'])],
        ]);

        if (!empty($result['answer']) && empty($result['error'])) {
            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie !',
                'preview' => $result['answer'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? "Échec de la connexion avec {$data['provider']}.",
        ], 422);
    }

    // ── DELETE /admin/ai-config/{id} ──────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        $config = AiConfig::findOrFail($id);

        if ($config->is_active && AiConfig::where('id', '!=', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Sélectionnez d’abord une autre clé API avant de supprimer la clé active.',
            ], 422);
        }

        $config->delete();
        
        return response()->json(['success' => true, 'message' => 'Configuration supprimée.']);
    }

    // ── GET /admin/ai-config/debug ────────────────────────────────────────
    // Endpoint de debug pour vérifier l'état de la clé API
    public function debug(): JsonResponse
    {
        $config = AiConfig::where('is_active', true)->latest()->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune config active trouvée',
            ]);
        }

        $apiKey = $config->api_key;
        return response()->json([
            'success' => true,
            'debug' => [
                'id' => $config->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'is_active' => $config->is_active,
                'label' => $config->label,
                'has_key' => !empty($apiKey),
                'key_hint' => $this->maskKey($apiKey ?? ''),
                'key_length' => strlen($apiKey ?? ''),
                'updated_at' => $config->updated_at,
                'created_at' => $config->created_at,
            ],
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────
    private function maskKey(string $key): string
    {
        if (strlen($key) < 8) return '••••••••';
        return substr($key, 0, 8) . str_repeat('•', min(12, strlen($key) - 8));
    }
}
