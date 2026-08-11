<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EscalationMail;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Documentation;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Projet;
use App\Services\CohereEmbeddingService;
use App\Services\N8nService;
use App\Services\RetrievalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AskController extends Controller
{
    // Nombre de tours précédents (question + réponse) inclus dans
    // l'historique envoyé à l'IA, pour qu'elle comprenne les relances
    // du type "envoie la suite de l'histoire" ou "et pour le deuxième
    // point ?" sans qu'on ait besoin de tout répéter.
    private const HISTORY_MAX_MESSAGES = 5;

    // Taille max de documentation en mode fallback (sans embeddings).
    // Réduit pour optimiser les coûts et le temps de réponse de n8n/Gemini
    private const FALLBACK_DOC_MAX_CHARS = 15000;  // Réduit de 50k à 15k

    public function __construct(
        private N8nService $n8n,
        private RetrievalService $retrieval,
        private CohereEmbeddingService $embeddings,
    ) {}

    public function __invoke(Request $request)
    {
        $data     = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'conversation_uuid' => ['nullable', 'string', 'max:36'],
            'conversation_id'   => ['nullable', 'integer']
        ]);
        $question = strip_tags(trim($data['question']));

        $clientId  = $request->session()->get('client_id');
        $projectId = $request->session()->get('project_id');

        if (!$clientId) {
            return response()->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        // Si pas de projet en session, essayer de récupérer le premier projet du client
        if (!$projectId) {
            $projet = Projet::where('client_id', $clientId)->first();
            if ($projet) {
                $request->session()->put('project_id', $projet->id);
                $request->session()->put('project_nom', $projet->nom_projet);
                $projectId = $projet->id;
            } else {
                return response()->json(['success' => false, 'error' => 'Aucun projet disponible pour ce client. Veuillez contacter l\'administrateur.'], 400);
            }
        }

        $projet = Projet::where('id', $projectId)->where('client_id', $clientId)->first();
        if (!$projet) {
            // Projet invalide en session, nettoyer et essayer avec le premier disponible
            $request->session()->forget(['project_id', 'project_nom']);
            $projet = Projet::where('client_id', $clientId)->first();
            if ($projet) {
                $request->session()->put('project_id', $projet->id);
                $request->session()->put('project_nom', $projet->nom_projet);
            } else {
                return response()->json(['success' => false, 'error' => 'Aucun projet disponible. Veuillez contacter l\'administrateur.'], 404);
            }
        }

        // ── Récupérer ou créer la conversation ──────────────────────────
        // Le frontend connaît toujours l'uuid de la conversation en cours
        // (il la restaure lui-même au chargement de la page). Un uuid
        // absent signifie donc explicitement "nouvelle conversation" (ex:
        // clic sur "+ Nouvelle conversation") — on ne doit surtout pas
        // deviner et reprendre la dernière conversation à sa place, sinon
        // ce bouton ne fonctionne jamais.
        $conversation = null;
        if (!empty($data['conversation_uuid'])) {
            $conversation = Conversation::where('uuid', $data['conversation_uuid'])
                ->where('client_id', $clientId)
                ->first();
        }
        if (!$conversation) {
            $conversation = Conversation::create([
                'client_id' => $clientId,
                'projet_id' => $projet->id,
                'status'    => 'open',
            ]);
        }

        // ── Sauvegarder la question ──────────────────────────────────────
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $question,
        ]);

        // ── 0) Conversation déjà escaladée et pas encore résolue ? ───────
        // Tant que le support n'a pas explicitement marqué la conversation
        // comme terminée, l'IA ne doit JAMAIS répondre à la place du
        // support : ça mélangerait deux interlocuteurs dans le même fil.
        // Le message du client est bien enregistré (le support le verra
        // apparaître en direct sur son écran de conversation) mais aucune
        // réponse automatique n'est générée.
        if ($conversation->status === 'escalated') {
            $conversation->touch();

            return response()->json([
                'success'           => true,
                'question'          => $question,
                'answer'            => null,
                'escalated'         => true,
                'waiting_human'     => true,
                'status'            => $conversation->status,
                'conversation_id'   => $conversation->id,
                'conversation_uuid' => $conversation->uuid,
                'user_message_id'   => $userMessage->id,
            ]);
        }

        // ── 1) Détection d'intention DÉSACTIVÉE ──
        // Toutes les questions passent directement au workflow N8N
        // qui gère lui-même les salutations, remerciements, etc.
        // Cela évite les timeouts et conflits avec le prompt système N8N.
        
        /*
        $client = Client::find($clientId);
        $intentResult = $this->detectIntent($question, $client?->name, $projet->nom_projet);
        if ($intentResult !== null) {
            $assistantMessage = Message::create([
                'conversation_id'  => $conversation->id,
                'role'             => 'assistant',
                'content'          => $intentResult['response'],
                'tokens_input'     => $intentResult['tokens_input'] ?? 0,
                'tokens_output'    => $intentResult['tokens_output'] ?? 0,
                'retrieval_source' => 'smalltalk', // Les salutations/intentions utilisent smalltalk
                'chunks_used'      => 0,
            ]);
            $conversation->touch();

            return response()->json([
                'success'              => true,
                'question'             => $question,
                'answer'               => $intentResult['response'],
                'escalated'            => false,
                'status'               => $conversation->status,
                'conversation_id'      => $conversation->id,
                'conversation_uuid'    => $conversation->uuid,
                'user_message_id'      => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
            ]);
        }
        */

        // ── 2) Documentation disponible pour ce projet ? ─────────────────
        $documentations = Documentation::where('projet_id', $projet->id)->with('faqs')->get();

        if ($documentations->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'Aucune documentation disponible pour ce projet'], 404);
        }

        // ── 3) Recherche sémantique (RAG, par intention) ─────────────────
        // Un seul appel : RetrievalService vérifie d'abord si une FAQ
        // correspond au SENS de la question (similarité cosinus ≥ 0.82,
        // pas un simple recoupement de mots) — si oui on répond instant-
        // anément avec la réponse apprise, sans appeler n8n. Sinon, il
        // renvoie les meilleurs passages de documentation par similarité
        // sémantique (ou la doc brute en repli si Cohere est indisponible).
        $retrieved = $this->retrieval->retrieve($question, $projet->id);

        if ($retrieved['source'] === 'faq' && $retrieved['faq']) {
            $learnedAnswer = $retrieved['faq']->reponse;
            $assistantMessage = Message::create([
                'conversation_id'  => $conversation->id,
                'role'             => 'assistant',
                'content'          => $learnedAnswer,
                'tokens_input'     => 0,
                'tokens_output'    => intdiv(mb_strlen($learnedAnswer), 4),
                'retrieval_source' => 'faq',
                'chunks_used'      => 0,
            ]);
            $conversation->update(['status' => $conversation->status === 'resolved' ? 'open' : $conversation->status]);
            $conversation->touch();

            return response()->json([
                'success'              => true,
                'question'             => $question,
                'answer'               => $learnedAnswer,
                'escalated'            => false,
                'status'               => $conversation->status,
                'conversation_id'      => $conversation->id,
                'conversation_uuid'    => $conversation->uuid,
                'user_message_id'      => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
            ]);
        }

        $docTexte = mb_substr(trim($retrieved['context']), 0, self::FALLBACK_DOC_MAX_CHARS);

        $faqTexte = '';
        foreach ($documentations as $doc) {
            foreach ($doc->faqs as $faq) {
                $faqTexte .= "Q: {$faq->question}\nR: {$faq->reponse}\n\n";
            }
        }

        // Historique de conversation : les échanges précédents (hors le
        // message qu'on vient de créer), pour que l'IA puisse résoudre
        // les relances qui ne se comprennent qu'avec le contexte
        // ("envoie la suite de l'histoire", "et pour le deuxième point ?").
        $history = $this->buildHistory($conversation, $userMessage->id);

        $chunksUsed  = isset($retrieved['chunks_count']) ? (int)$retrieved['chunks_count'] : 0;
        $n8nResponse = $this->n8n->askAssistant(
            question:      $question,
            documentation: trim($docTexte),
            faq:           trim($faqTexte),
            history:       $history,
            clientName:    $client?->name ?? 'Client',
        );

        $answer   = $n8nResponse['answer'];
        $escalate = $n8nResponse['escalate'] ?? false;

        $assistantMessage = Message::create([
            'conversation_id'  => $conversation->id,
            'role'             => 'assistant',
            'content'          => $answer,
            'tokens_input'     => $n8nResponse['tokens_input']  ?? 0,
            'tokens_output'    => $n8nResponse['tokens_output'] ?? 0,
            'retrieval_source' => $retrieved['source'] ?? 'fallback',
            'chunks_used'      => $chunksUsed,
        ]);

        // ── Escalade si l'IA ne sait pas répondre ───────────────────────
        if ($escalate) {
            $conversation->update([
                'status'           => 'escalated',
                'escalated_at'     => now(),
                'pending_question' => $question,
            ]);

            $supportEmail = config('services.support.email');
            $frontendUrl  = config('services.support.frontend_url');
            $convUrl      = "{$frontendUrl}/support/conversation/{$conversation->uuid}";

            if ($supportEmail) {
                try {
                    Mail::to($supportEmail)->send(new EscalationMail(
                        clientName:       $client?->name ?? 'Client',
                        projetName:       $projet->nom_projet,
                        question:         $question,
                        conversationUrl:  $convUrl,
                        conversationUuid: $conversation->uuid,
                    ));
                } catch (\Throwable $e) {
                    // L'échec d'envoi de l'email ne doit jamais empêcher le
                    // client de recevoir sa réponse — l'escalade reste bien
                    // enregistrée en base, seule la notification échoue.
                    report($e);
                }
            }
        } else {
            // Une réponse "normale" de l'IA referme définitivement le
            // bandeau "résolu" éventuellement affiché — la conversation
            // redevient simplement active.
            $conversation->update(['status' => $conversation->status === 'resolved' ? 'open' : $conversation->status]);
            $conversation->touch();
        }

        return response()->json([
            'success'              => true,
            'question'             => $question,
            'answer'               => $answer,
            'escalated'            => $escalate,
            'status'               => $conversation->fresh()->status,
            'conversation_id'      => $conversation->id,
            'conversation_uuid'    => $conversation->uuid,
            'user_message_id'      => $userMessage->id,
            'assistant_message_id' => $assistantMessage->id,
        ]);
    }

    // ── Construit l'historique récent de la conversation, formaté pour ──
    // être lisible par l'IA (rôle : contenu). On exclut le message qu'on
    // vient tout juste de créer : il est déjà envoyé séparément comme
    // "question" ; l'historique ne doit contenir que ce qui précède.
    private function buildHistory(Conversation $conversation, int $excludeMessageId): string
    {
        // Récupérer les N derniers messages (hors le message courant)
        // On trie par ID décroissant pour prendre les plus récents, puis on inverse pour avoir l'ordre chronologique
        $messages = $conversation->messages()
            ->where('id', '!=', $excludeMessageId)
            ->orderBy('id', 'desc')  // Plus récent d'abord
            ->take(self::HISTORY_MAX_MESSAGES)
            ->get()
            ->sortBy('id')  // Retrier par ID croissant pour ordre chronologique
            ->values();     // Réindexer la collection

        if ($messages->isEmpty()) {
            return '';
        }

        $labels = [
            'user'          => 'Client',
            'assistant'     => 'Assistant',
            'human_support' => 'Support',
        ];

        return $messages->map(function (Message $m) use ($labels) {
            $label = $labels[$m->role] ?? $m->role;
            return "{$label} : {$m->content}";
        })->implode("\n");
    }

    // ── Détection d'intention intelligente avec IA ─────────────────────
    private function detectIntent(string $question, ?string $clientName, string $projetName): ?array
    {
        // Messages très courts et évidents (optimisation)
        $quickPatterns = $this->quickIntentPatterns($question, $clientName, $projetName);
        if ($quickPatterns !== null) {
            return $quickPatterns;
        }

        // Détection par IA pour intentions plus complexes
        return $this->aiIntentDetection($question, $clientName, $projetName);
    }

    private function quickIntentPatterns(string $question, ?string $clientName, string $projetName): ?array
    {
        $normalized = $this->stripAccents(mb_strtolower(trim($question)));
        $normalized = preg_replace('/[^\p{L}\s]/u', '', $normalized);
        $name = $clientName ? $clientName : '';

        // Salutations évidentes et courtes
        if (preg_match('/^(hello+|hi+|salut+|yo+|hey+|bonjour+|bonsoir+|cc+|coucou+)$/', $normalized)) {
            return [
                'response' => trim("Bonjour {$name} ! Comment puis-je vous aider concernant « {$projetName} » ?"),
                'source' => 'greeting',
                'tokens_input' => 0,
                'tokens_output' => 0
            ];
        }

        // Salutations avec "comment" (comment ça va, comment allez-vous, etc.)
        if (preg_match('/^comment\s+(ca|ça)\s+va\s*\?*$/', $normalized) ||
            preg_match('/^comment\s+(allez\s*vous|vas\s*tu|tu\s+vas)\s*\?*$/', $normalized) ||
            preg_match('/^(ca|ça)\s+va\s*(bien)?\s*\?*$/', $normalized) ||
            preg_match('/^how\s+are\s+you(\s+doing)?\s*\?*$/', $normalized) ||
            preg_match('/^whats?\s+up\s*\?*$/', $normalized)) {
            return [
                'response' => trim("Je vais très bien, merci {$name} ! Et vous ? Comment puis-je vous aider concernant « {$projetName} » ?"),
                'source' => 'greeting_conversation',
                'tokens_input' => 0,
                'tokens_output' => 0
            ];
        }

        // Remerciements évidents
        if (preg_match('/^(merci|thanks?|merci beaucoup)$/', $normalized)) {
            return [
                'response' => "Avec plaisir ! N'hésitez pas si vous avez d'autres questions.",
                'source' => 'thanks',
                'tokens_input' => 0,
                'tokens_output' => 0
            ];
        }

        // Clôture de conversation
        if (preg_match('/^(c\'?est\s+(bon|regle|resolu)|ca\s+marche|ok\s+c\'?est\s+bon)$/', $normalized)) {
            return [
                'response' => "Parfait, je suis ravi que ce soit résolu ! N'hésitez pas si vous avez d'autres questions.",
                'source' => 'closure',
                'tokens_input' => 0,
                'tokens_output' => 0
            ];
        }

        // Contenu inapproprié - détection de mots vulgaires (mot entier uniquement)
        $inappropriateWords = [
            'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'damn', 'crap',
            'connard', 'salaud', 'putain', 'merde', 'enculé', 'enculer',
            'bordel', 'chier', 'emmerde', 'fils de pute', 'fdp', 'pute',
            'idiot', 'imbecile', 'crétin', 'débile', 'stupide'
        ];
        
        // Utiliser des word boundaries pour éviter les faux positifs (ex: "con" dans "confirmation")
        foreach ($inappropriateWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $normalized)) {
                return [
                    'response' => "Je comprends, mais je préférerais que nous gardions une conversation professionnelle. Comment puis-je vous aider concernant {$projetName} ?",
                    'source' => 'moderation',
                    'tokens_input' => 0,
                    'tokens_output' => 0
                ];
            }
        }

        return null;        return null;
    }

    private function aiIntentDetection(string $question, ?string $clientName, string $projetName): ?array
    {
        // Utiliser Gemini pour la détection d'intention (plus fiable que Cohere pour ce cas)
        $geminiKey = config('services.gemini.api_key');
        if (!$geminiKey) {
            return null;
        }

        $prompt = "Classifie cette phrase en UNE de ces 5 catégories. Répond SEULEMENT par le chiffre correspondant :

1 = Salutation (bonjour, hello, comment ça va, ça va, how are you)
2 = Contenu inapproprié (insultes, vulgarité, racisme)  
3 = Remerciement (merci, thanks)
4 = Problème résolu (c'est bon, c'est réglé, ça marche)
5 = Question technique (nécessite documentation)

Phrase : \"{$question}\"
Réponse (juste le chiffre) :";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 5,
                    'temperature' => 0.0,
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $intent = trim($text);
                
                // Convertir le chiffre en intention
                $intentMap = [
                    '1' => 'GREETING',
                    '2' => 'INAPPROPRIATE', 
                    '3' => 'THANKS',
                    '4' => 'CLOSURE',
                    '5' => 'TECHNICAL'
                ];
                
                $mappedIntent = $intentMap[$intent] ?? 'TECHNICAL';
                return $this->handleDetectedIntent($mappedIntent, $question, $clientName, $projetName);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, continuer normalement (pas critique)
            return null;
        }

        return null;
    }

    private function handleDetectedIntent(string $intent, string $question, ?string $clientName, string $projetName): ?array
    {
        $name = $clientName ? $clientName : '';

        switch ($intent) {
            case 'GREETING':
                return [
                    'response' => trim("Bonjour {$name} ! Comment puis-je vous aider concernant « {$projetName} » ?"),
                    'source' => 'greeting_ai',
                    'tokens_input' => 5,
                    'tokens_output' => 2
                ];

            case 'INAPPROPRIATE':
                return [
                    'response' => "Je comprends, mais je préférerais que nous gardions une conversation professionnelle. Comment puis-je vous aider concernant {$projetName} ?",
                    'source' => 'moderation',
                    'tokens_input' => 5,
                    'tokens_output' => 2
                ];

            case 'THANKS':
                return [
                    'response' => "Je vous en prie ! N'hésitez pas si vous avez d'autres questions concernant {$projetName}.",
                    'source' => 'thanks_ai',
                    'tokens_input' => 5,
                    'tokens_output' => 2
                ];

            case 'CLOSURE':
                return [
                    'response' => "Parfait, je suis ravi que ce soit résolu ! N'hésitez pas si vous avez d'autres questions.",
                    'source' => 'closure',
                    'tokens_input' => 5,
                    'tokens_output' => 2
                ];

            case 'TECHNICAL':
            default:
                // Continuer avec le traitement normal (n8n)
                return null;
        }
    }

    // ── Détection salutations + small-talk (jamais escaladé, jamais n8n) ─
    private function smallTalkAnswer(string $question, ?string $clientName, string $projetName): ?string
    {
        $normalized = $this->stripAccents(mb_strtolower(trim($question)));
        $normalized = preg_replace('/[^\p{L}\s]/u', '', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        $name = $clientName ? $clientName : '';

        // Salutations simples : réponse d'accueil
        // Ajout de patterns pour gérer les variations (helloooo, saaaalut, etc.)
        $greetingPatterns = [
            '/^s+a+l+u+t+$/',        // salut avec répétitions (saaaalut, etc.)
            '/^h+e+l+o+$/',          // hello avec répétitions (helloooo, etc.)
            '/^h+i+$/',              // hi avec répétitions (hiii, etc.)
            '/^h+e+y+$/',            // hey avec répétitions (heyyyy, etc.)
            '/^b+o+n+j+o+u+r+$/',    // bonjour avec répétitions
            '/^b+o+n+s+o+i+r+$/',    // bonsoir avec répétitions
            '/^c+o+u+c+o+u+$/',      // coucou avec répétitions
            '/^y+o+$/',              // yo avec répétitions (yooo, etc.)
            '/^c+$/',                // cc avec répétitions (cccc, etc.)
        ];
        
        $staticGreetings = ['salut', 'yo', 'hello', 'hi', 'coucou', 'bonjour', 'bonsoir',
            'cc', 'slt', 'hey', 'bjr', 'bsr', 'wesh', 'good morning', 'good evening', 'howdy'];

        $words = array_values(array_filter(explode(' ', $normalized)));
        if (count($words) > 0 && count($words) <= 3) {
            // Vérifier les salutations statiques
            foreach ($staticGreetings as $g) {
                if ($normalized === $g || $words[0] === $g) {
                    return trim("Bonjour {$name} ! Comment puis-je vous aider concernant « {$projetName} » ?");
                }
            }
            
            // Vérifier les patterns (helloooo, etc.)
            foreach ($greetingPatterns as $pattern) {
                if (preg_match($pattern, $normalized)) {
                    return trim("Bonjour {$name} ! Comment puis-je vous aider concernant « {$projetName} » ?");
                }
            }
        }

        // "Comment vas-tu / ça va / comment allez-vous" : small talk
        $smallTalkPatterns = [
            '/^comment (vas[ -]?tu|allez[ -]?vous|va tu)\b/',
            '/^(ca|ça) va\b/',
            '/^tu vas bien\b/',
            '/^vous allez bien\b/',
            '/^comment tu vas\b/',
            '/^how are you\b/',
            '/^whats up\b/',
        ];
        foreach ($smallTalkPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return trim("Je vais très bien, merci ! Et vous ? En tout cas je suis prêt à vous aider concernant « {$projetName} », posez-moi votre question.");
            }
        }

        // Remerciements
        $thanks = ['merci', 'merci beaucoup', 'thanks', 'thank you', 'ok merci'];
        if (in_array($normalized, $thanks, true)) {
            return "Avec plaisir ! N'hésitez pas si vous avez d'autres questions.";
        }

        return null;
    }

    private function stripAccents(string $text): string
    {
        $unwanted = ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
        return strtr($text, $unwanted);
    }

    // ── Consulter une conversation (support humain, via lien email) ─────
    public function show(string $uuid)
    {
        $conversation = Conversation::with(['messages', 'client', 'projet'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'success'      => true,
            'conversation' => $this->serializeConversation($conversation),
        ]);
    }

    // ── Consulter SA conversation par uuid (client, session requise) ────
    public function showForClient(Request $request, string $uuid)
    {
        $clientId = $request->session()->get('client_id');

        $conversation = Conversation::with(['messages', 'client', 'projet'])
            ->where('uuid', $uuid)
            ->where('client_id', $clientId)
            ->firstOrFail();

        return response()->json([
            'success'      => true,
            'conversation' => $this->serializeConversation($conversation),
        ]);
    }

    // ── Consulter SA conversation la plus récente pour le projet courant ─
    // Utilisé au chargement du chat pour restaurer l'historique après un
    // rafraîchissement de page.
    public function latestForClient(Request $request)
    {
        $clientId  = $request->session()->get('client_id');
        $projectId = $request->session()->get('project_id');

        if (!$clientId || !$projectId) {
            return response()->json(['success' => true, 'conversation' => null]);
        }

        $conversation = Conversation::with(['messages', 'client', 'projet'])
            ->where('client_id', $clientId)
            ->where('projet_id', $projectId)
            ->latest('updated_at')
            ->first();

        return response()->json([
            'success'      => true,
            'conversation' => $conversation ? $this->serializeConversation($conversation) : null,
        ]);
    }

    private function serializeConversation(Conversation $conversation): array
    {
        return [
            'uuid'       => $conversation->uuid,
            'status'     => $conversation->status,
            'client'     => $conversation->client?->name,
            'projet'     => $conversation->projet?->nom_projet,
            'created_at' => $conversation->created_at,
            'messages'   => $conversation->messages->map(fn($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'content'    => $m->content,
                'created_at' => $m->created_at,
            ]),
        ];
    }

    // ── Réponse du support humain (peut être appelée plusieurs fois) ────
    // L'apprentissage FAQ est TOUJOURS automatique — aucune case à cocher,
    // aucune décision manuelle possible côté support.
    public function humanReply(Request $request, string $uuid)
    {
        $data = $request->validate([
            'reply'   => ['required', 'string', 'max:5000'],
            'resolve' => ['boolean'],
        ]);

        $conversation = Conversation::with('projet')->where('uuid', $uuid)->firstOrFail();
        $replyText    = strip_tags(trim($data['reply']));

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'human_support',
            'content'         => $replyText,
        ]);

        // ── Apprentissage automatique systématique ──────────────────────
        $questionToLearn = $conversation->pending_question
            ?? $conversation->messages()->where('role', 'user')->latest()->value('content');

        if ($questionToLearn) {
            $doc = Documentation::where('projet_id', $conversation->projet_id)->first();

            if ($doc) {
                // Embedding de la question à apprendre : sert à la fois à
                // repérer un doublon sémantique existant (ci-dessous) et à
                // rendre cette FAQ trouvable par RetrievalService ensuite —
                // sans lui, cette FAQ ne serait JAMAIS retrouvée par la
                // recherche par intention (elle ne cherche que parmi les
                // FAQ ayant un embedding).
                $newVector = $this->embeddings->embed($questionToLearn);

                // Upsert : si une FAQ très proche du SENS de cette question
                // existe déjà, on met à jour sa réponse plutôt que d'en
                // créer une deuxième (évite les doublons en cas de réponses
                // successives sur le même sujet, même reformulé).
                $existing = null;
                if ($newVector !== null) {
                    $bestScore = 0.0;
                    foreach (Faq::where('documentation_id', $doc->id)->whereNotNull('embedding')->get() as $faq) {
                        $faqVector = json_decode($faq->embedding, true);
                        if (!is_array($faqVector)) continue;
                        $score = CohereEmbeddingService::cosineSimilarity($newVector, $faqVector);
                        if ($score > $bestScore) { $bestScore = $score; $existing = $faq; }
                    }
                    if ($bestScore < 0.85) { $existing = null; }
                }

                $embeddingJson = $newVector ? json_encode($newVector) : null;

                if ($existing) {
                    $existing->update(['reponse' => $replyText, 'embedding' => $embeddingJson]);
                } else {
                    Faq::create([
                        'documentation_id' => $doc->id,
                        'question'         => $questionToLearn,
                        'reponse'          => $replyText,
                        'embedding'        => $embeddingJson,
                    ]);
                }
            }
        }

        // Le statut reste "escalated" tant que le support n'a pas
        // explicitement marqué la conversation comme résolue — il peut
        // donc répondre plusieurs fois de suite sans que ça se ferme seul.
        // pending_question n'est effacée qu'à la résolution : tant que la
        // conversation reste ouverte, chaque nouvelle réponse humaine doit
        // continuer à apprendre sur LA MÊME question d'origine.
        if (!empty($data['resolve'])) {
            $conversation->update(['status' => 'resolved', 'pending_question' => null]);
        } elseif ($conversation->status !== 'escalated') {
            $conversation->update(['status' => 'escalated']);
        }

        return response()->json([
            'success' => true,
            'message' => !empty($data['resolve']) ? 'Réponse envoyée, conversation résolue.' : 'Réponse envoyée.',
            'data'    => [
                'id'         => $message->id,
                'role'       => $message->role,
                'content'    => $message->content,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    // ── Liste conversations pour l'admin ────────────────────────────────
    public function adminList()
    {
        $conversations = Conversation::with(['client:id,name', 'projet:id,nom_projet'])
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'uuid'        => $c->uuid,
                'status'      => $c->status,
                'client_name' => $c->client?->name,
                'projet_name' => $c->projet?->nom_projet,
                // ✅ Si escaladé, afficher la question d'escalade, sinon le dernier message
                'last_message'=> $c->status === 'escalated' 
                    ? $c->messages()->where('role', 'user')->latest()->value('content')
                    : $c->messages()->latest()->value('content'),
                'updated_at'  => $c->updated_at,
            ]);
        return response()->json(['success' => true, 'data' => $conversations]);
    }

    // ── Supprimer conversation (admin) ──────────────────────────────────
    public function adminDeleteConversation(int $id)
    {
        $conversation = Conversation::find($id);
        
        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation introuvable'], 404);
        }

        // Supprimer tous les messages associés
        $conversation->messages()->delete();
        
        // Supprimer la conversation
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation supprimée avec succès'
        ]);
    }

    // ── Liste conversations du client connecté ──────────────────────────
    public function clientList(\Illuminate\Http\Request $request)
    {
        $clientId  = $request->session()->get('client_id');
        $projectId = $request->session()->get('project_id');
        
        // ✅ Filtrer par projet sélectionné (si défini)
        $query = Conversation::where('client_id', $clientId);
        
        if ($projectId) {
            $query->where('projet_id', $projectId);
        }
        
        $convs = $query->orderByDesc('updated_at')
            ->get(['id','uuid','status','projet_id','created_at','updated_at']);
            
        return response()->json(['success' => true, 'data' => $convs]);
    }

    // ── Créer une nouvelle conversation ────────────────────────────────
    public function createConversation(\Illuminate\Http\Request $request)
    {
        $data     = $request->validate(['projet_id' => ['required', 'integer']]);
        $clientId = $request->session()->get('client_id');
        $conv = Conversation::create([
            'client_id' => $clientId,
            'projet_id' => $data['projet_id'],
            'uuid'      => (string) \Illuminate\Support\Str::uuid(),
            'status'    => 'open',
        ]);
        return response()->json(['success' => true, 'conversation' => $conv]);
    }

    // ── Afficher une conversation par ID (client) ───────────────────────
    public function showConversation(\Illuminate\Http\Request $request, int $id)
    {
        $clientId = $request->session()->get('client_id');
        $conv = Conversation::with('messages')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->firstOrFail();
        return response()->json([
            'success'      => true,
            'conversation' => ['id' => $conv->id, 'uuid' => $conv->uuid, 'status' => $conv->status],
            'messages'     => $conv->messages->map(fn($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'content'    => $m->content,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    // ── Supprimer une conversation (client) ──────────────────────────────
    public function deleteConversation(\Illuminate\Http\Request $request, int $id)
    {
        $clientId = $request->session()->get('client_id');
        $conv = Conversation::where('id', $id)
            ->where('client_id', $clientId)
            ->firstOrFail();
        
        // Supprimer les messages associés
        Message::where('conversation_id', $conv->id)->delete();
        
        // Supprimer la conversation
        $conv->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Conversation supprimée',
        ]);
    }

}