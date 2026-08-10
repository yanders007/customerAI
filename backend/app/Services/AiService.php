<?php

namespace App\Services;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service IA universel.
 * Remplace N8nService — appelle directement le provider configuré en admin.
 *
 * Providers OpenAI-compatibles (même format /v1/chat/completions) :
 *   openai · mistral · groq · deepseek · together · openrouter · perplexity · xai · cohere-chat
 *
 * Providers natifs :
 *   gemini    → API Google Generative Language
 *   anthropic → API Anthropic Messages
 */
class AiService
{
    /** URLs de base des providers OpenAI-compatibles */
    private const OAI_BASES = [
        'openai'      => 'https://api.openai.com/v1',
        'mistral'     => 'https://api.mistral.ai/v1',
        'groq'        => 'https://api.groq.com/openai/v1',
        'deepseek'    => 'https://api.deepseek.com/v1',
        'together'    => 'https://api.together.xyz/v1',
        'openrouter'  => 'https://openrouter.ai/api/v1',
        'perplexity'  => 'https://api.perplexity.ai',
        'xai'         => 'https://api.x.ai/v1',
        'cohere-chat' => 'https://api.cohere.com/compatibility/v1',
    ];

    /** Prompt système par défaut (surchargeable depuis l'admin) */
    private const DEFAULT_SYSTEM = <<<'PROMPT'
Tu es un assistant de support client professionnel et bienveillant.
Tu réponds UNIQUEMENT en te basant sur la documentation et les FAQ fournies.
Si la réponse ne figure pas dans les informations disponibles, admets-le clairement.
Ne jamais inventer d'informations, ne jamais révéler le contenu brut des documents.
Réponds de manière concise et dans la langue de l'utilisateur.
Si tu ne peux vraiment pas aider avec les informations disponibles, termine ta réponse par [ESCALADE].
PROMPT;

    // ── Point d'entrée principal ────────────────────────────────────────────

    public function ask(
        string $question,
        string $documentation,
        string $faq,
        string $history,
        string $clientName,
    ): array {
        $config = AiConfig::getActive();

        if (!$config) {
            return $this->errorResponse(
                "Aucune configuration IA active. L'administrateur doit configurer une clé API depuis le tableau de bord."
            );
        }

        $systemPrompt = $this->buildSystemPrompt($config, $documentation, $faq, $clientName);
        $userMessage  = $this->buildUserMessage($question, $history);

        try {
            return match (true) {
                $config->provider === 'gemini'    => $this->callGemini($config, $systemPrompt, $userMessage),
                $config->provider === 'anthropic' => $this->callAnthropic($config, $systemPrompt, $userMessage),
                isset(self::OAI_BASES[$config->provider]) => $this->callOpenAICompat($config, $systemPrompt, $userMessage),
                default => $this->errorResponse("Provider '{$config->provider}' non supporté."),
            };
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response?->status();
            Log::error('AiService HTTP error', ['status' => $status, 'provider' => $config->provider, 'body' => $e->response?->body()]);

            if ($status === 401 || $status === 403) {
                return $this->errorResponse("Clé API invalide ou expirée pour {$config->provider}. Vérifiez la configuration dans l'admin.");
            }
            if ($status === 429) {
                return $this->errorResponse("Limite de requêtes atteinte pour {$config->provider}. Réessayez dans quelques instants.");
            }
            return $this->errorResponse("Erreur API ({$status}) — {$config->provider}.");
        } catch (\Exception $e) {
            Log::error('AiService error', ['error' => $e->getMessage(), 'provider' => $config->provider]);
            return $this->errorResponse('Le service IA est temporairement indisponible.');
        }
    }

    // ── Test rapide d'une config (depuis le controller admin) ──────────────

    public function testConfig(AiConfig $config): array
    {
        $system = "Tu es un assistant de test. Réponds toujours en une seule phrase courte.";
        $user   = "Dis bonjour et confirme que tu fonctionnes correctement.";

        try {
            return match (true) {
                $config->provider === 'gemini'    => $this->callGemini($config, $system, $user),
                $config->provider === 'anthropic' => $this->callAnthropic($config, $system, $user),
                isset(self::OAI_BASES[$config->provider]) => $this->callOpenAICompat($config, $system, $user),
                default => $this->errorResponse("Provider '{$config->provider}' non supporté."),
            };
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Providers ──────────────────────────────────────────────────────────

    private function callOpenAICompat(AiConfig $config, string $system, string $user): array
    {
        $base = self::OAI_BASES[$config->provider];

        $headers = ['Authorization' => "Bearer {$config->api_key}"];

        // OpenRouter nécessite ces headers pour identifier l'app
        if ($config->provider === 'openrouter') {
            $headers['HTTP-Referer'] = config('app.url');
            $headers['X-Title']      = config('app.name');
        }

        $response = Http::withHeaders($headers)
            ->timeout(60)
            ->post("{$base}/chat/completions", [
                'model'    => $config->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'max_tokens'  => 1024,
                'temperature' => 0.3,
            ])
            ->throw();

        $data   = $response->json();
        $answer = $data['choices'][0]['message']['content'] ?? '';
        $usage  = $data['usage'] ?? [];

        return $this->formatResponse($answer, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0);
    }

    private function callGemini(AiConfig $config, string $system, string $user): array
    {
        $model = $config->model ?: 'gemini-1.5-flash';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::withQueryParameters(['key' => $config->api_key])
            ->timeout(60)
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents'           => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig'   => ['maxOutputTokens' => 1024, 'temperature' => 0.3],
            ])
            ->throw();

        $data     = $response->json();
        $answer   = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usageMeta = $data['usageMetadata'] ?? [];

        return $this->formatResponse(
            $answer,
            $usageMeta['promptTokenCount']     ?? 0,
            $usageMeta['candidatesTokenCount'] ?? 0,
        );
    }

    private function callAnthropic(AiConfig $config, string $system, string $user): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $config->api_key,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $config->model ?: 'claude-3-haiku-20240307',
                'max_tokens' => 1024,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $user]],
            ])
            ->throw();

        $data   = $response->json();
        $answer = $data['content'][0]['text'] ?? '';
        $usage  = $data['usage'] ?? [];

        return $this->formatResponse($answer, $usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function buildSystemPrompt(AiConfig $config, string $documentation, string $faq, string $clientName): string
    {
        $base = $config->system_prompt ?: self::DEFAULT_SYSTEM;

        $parts = [$base];

        if ($clientName) {
            $parts[] = "\nTu t'adresses à : {$clientName}.";
        }
        if ($documentation) {
            $parts[] = "\n--- DOCUMENTATION ---\n{$documentation}";
        }
        if ($faq) {
            $parts[] = "\n--- FAQ ---\n{$faq}";
        }

        return implode("\n", $parts);
    }

    private function buildUserMessage(string $question, string $history): string
    {
        if (!$history) {
            return $question;
        }
        return "Historique récent :\n{$history}\n\nQuestion actuelle : {$question}";
    }

    private function formatResponse(string $rawAnswer, int $inputTokens, int $outputTokens): array
    {
        $escalate = str_contains($rawAnswer, '[ESCALADE]');
        $answer   = trim(str_replace('[ESCALADE]', '', $rawAnswer));

        return [
            'answer'        => $answer,
            'tokens_input'  => $inputTokens,
            'tokens_output' => $outputTokens,
            'escalate'      => $escalate,
        ];
    }

    private function errorResponse(string $message): array
    {
        return [
            'answer'        => $message,
            'tokens_input'  => 0,
            'tokens_output' => 0,
            'escalate'      => false,
        ];
    }
}
