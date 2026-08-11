<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
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
                'provider'      => $c->provider,
                'model'         => $c->model,
                'is_active'     => $c->is_active,
                'api_key_hint'  => $this->maskKey($c->api_key),
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

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $config->id,
                'provider'      => $config->provider,
                'model'         => $config->model,
                'is_active'     => $config->is_active,
                'system_prompt' => $config->system_prompt,
                'api_key_hint'  => $this->maskKey($config->api_key),
                'has_key'       => !empty($config->api_key),
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
            'system_prompt' => ['nullable', 'string', 'max:5000'],
        ]);

        // Désactiver toutes les configs précédentes
        AiConfig::where('is_active', true)->update(['is_active' => false]);

        // Créer une nouvelle config (le mutateur chiffrera automatiquement api_key)
        $config = new AiConfig([
            'provider'      => $data['provider'],
            'model'         => $data['model'],
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
        $config->delete();
        
        return response()->json(['success' => true, 'message' => 'Configuration supprimée.']);
    }

    // ── Helper ────────────────────────────────────────────────────────────
    private function maskKey(string $key): string
    {
        if (strlen($key) < 8) return '••••••••';
        return substr($key, 0, 8) . str_repeat('•', min(12, strlen($key) - 8));
    }
}
