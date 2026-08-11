<?php
namespace App\Services;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nService
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.webhook_url', '');
    }

    /**
     * Envoie une question + contexte RAG à n8n/Gemini et retourne
     * la réponse ainsi que les tokens consommés.
     *
     * @return array{ answer: string, tokens_input: int, tokens_output: int, escalate: bool }
     */
    public function askAssistant(
        string $question,
        string $documentation,
        string $faq,
        string $history,
        string $clientName,
    ): array {
        if (empty($this->webhookUrl)) {
            return [
                'answer'        => 'Service IA non configuré.',
                'tokens_input'  => 0,
                'tokens_output' => 0,
                'escalate'      => false,
            ];
        }

        // Récupérer la configuration IA active (modèle + clé API)
        $aiConfig = AiConfig::getActive();
        
        if (!$aiConfig) {
            return [
                'answer'        => 'Aucune configuration IA active. L\'administrateur doit configurer une clé API depuis le tableau de bord.',
                'tokens_input'  => 0,
                'tokens_output' => 0,
                'escalate'      => true,
            ];
        }

        // Log de la taille du contexte envoyé pour optimisation
        Log::debug('N8nService: envoi à n8n/Gemini', [
            'provider'        => $aiConfig->provider,
            'model'           => $aiConfig->model,
            'question_length' => mb_strlen($question),
            'doc_length'      => mb_strlen($documentation),
            'faq_length'      => mb_strlen($faq),
            'history_length'  => mb_strlen($history),
            'total_chars'     => mb_strlen($question . $documentation . $faq . $history),
        ]);

        try {
            $response = Http::timeout(180) // 3 minutes pour N8N (cold start + processing)
                ->post($this->webhookUrl, [
                    'question'      => $question,
                    'documentation' => $documentation,
                    'faq'           => $faq,
                    'history'       => $history,
                    'client_name'   => $clientName,
                    // Passer la config IA à N8N
                    'provider'      => $aiConfig->provider,
                    'model'         => $aiConfig->model,
                    'api_key'       => $aiConfig->api_key, // Déchiffré automatiquement par l'accesseur
                ]);

            if (!$response->successful()) {
                Log::error('N8nService: HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                
                return [
                    'answer'        => "Je suis désolé, notre assistant est temporairement indisponible. Un membre de notre équipe va vous répondre dans les plus brefs délais. Vous pouvez aussi nous contacter à " . config('services.support.email', 'support@example.com'),
                    'tokens_input'  => 0,
                    'tokens_output' => 0,
                    'escalate'      => true, // Escalade automatique si n8n down
                ];
            }

            $data = $response->json();

            // Log de la réponse N8N pour debug
            Log::info('[N8N-RESPONSE]', [
                'answer' => $data['answer'] ?? 'NO ANSWER',
                'raw_data' => $data,
            ]);

            if (!$data || !isset($data['answer'])) {
                Log::error('N8nService: champ answer manquant', ['body' => $response->body()]);
                
                return [
                    'answer'        => "Je suis désolé, notre assistant est temporairement indisponible. Un membre de notre équipe va vous répondre dans les plus brefs délais. Vous pouvez aussi nous contacter à " . config('services.support.email', 'support@example.com'),
                    'tokens_input'  => 0,
                    'tokens_output' => 0,
                    'escalate'      => true,
                ];
            }

            // n8n peut retourner les tokens s'ils sont exposés dans le workflow Gemini
            // Sinon on estime : ~4 chars = 1 token (approximation grossière)
            $tokensInput  = (int) ($data['tokens_input']  ?? $data['usage']['input_tokens']  ?? intdiv(mb_strlen($documentation . $faq . $history . $question), 4));
            $tokensOutput = (int) ($data['tokens_output'] ?? $data['usage']['output_tokens'] ?? intdiv(mb_strlen($data['answer']), 4));

            return [
                'answer'        => $data['answer'],
                'tokens_input'  => $tokensInput,
                'tokens_output' => $tokensOutput,
                'escalate'      => $data['escalate'] ?? false,  // Récupérer le champ escalate de n8n
            ];
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Timeout ou connexion impossible (n8n down, réseau coupé, etc.)
            Log::error('N8nService: connexion impossible', [
                'error'   => $e->getMessage(),
                'webhook' => $this->webhookUrl,
            ]);
            
            return [
                'answer'        => "Je suis désolé, notre assistant est temporairement indisponible. Un membre de notre équipe va vous répondre dans les plus brefs délais. Vous pouvez aussi nous contacter à " . config('services.support.email', 'support@example.com'),
                'tokens_input'  => 0,
                'tokens_output' => 0,
                'escalate'      => true, // Escalade automatique
            ];
            
        } catch (\Exception $e) {
            // Toute autre erreur imprévue
            Log::error('N8nService: erreur inattendue', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            
            return [
                'answer'        => "Je suis désolé, notre assistant est temporairement indisponible. Un membre de notre équipe va vous répondre dans les plus brefs délais. Vous pouvez aussi nous contacter à " . config('services.support.email', 'support@example.com'),
                'tokens_input'  => 0,
                'tokens_output' => 0,
                'escalate'      => true,
            ];
        }
    }

    /**
     * Réponse humaine lors d'une escalade.
     */
    public function humanReply(string $reply, string $context): string
    {
        return $reply;
    }
}
