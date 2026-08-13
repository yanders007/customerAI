<?php

namespace App\Services;

use App\Models\DocumentationChunk;
use App\Models\Faq;
use App\Models\Projet;
use Illuminate\Support\Facades\Log;

/**
 * RetrievalService — Récupération des passages les plus pertinents.
 *
 * Pipeline en 4 étapes :
 *
 *  1. FAQ exacte  : si une FAQ répond directement à la question
 *     (similarité > FAQ_THRESHOLD), on la retourne immédiatement
 *     sans toucher aux chunks — réponse quasi instantanée.
 *
 *  2. Embedding de la question : vecteur Cohere de la requête.
 *
 *  3. Similarité cosinus sur les chunks : on parcourt les chunks
 *     du projet (ou de tous les projets) et on retient ceux dont
 *     le score dépasse CHUNK_THRESHOLD.
 *
 *  4. Déduplication sémantique (MMR simplifié) : parmi les chunks
 *     retenus, on écarte ceux qui sont trop proches d'un chunk déjà
 *     sélectionné (similarité inter-chunks > DIVERSITY_THRESHOLD).
 *     Cela évite d'envoyer deux paragraphes quasi identiques à l'IA
 *     et accélère le traitement (moins de tokens consommés).
 *
 * Fallback : si Cohere n'est pas disponible ou qu'aucun chunk ne
 * dépasse le seuil, on renvoie les 50 000 premiers caractères du
 * contenu brut de la documentation (comportement original).
 */
class RetrievalService
{
    // ── Seuils ──────────────────────────────────────────────────────
    // FAQ : score minimum pour considérer qu'une FAQ répond à la question
    private const FAQ_THRESHOLD = 0.75;  // Réponse directe uniquement si la correspondance est forte

    // FAQ secondaires autorisées dans le contexte du modèle, sans réponse directe.
    private const FAQ_CONTEXT_THRESHOLD = 0.50;
    private const MAX_FAQ_CONTEXT      = 3;

    // Chunks : score minimum pour inclure un chunk dans le contexte
    // Seuil calibré après tests : 0.42 pour capturer les questions courtes/vagues
    private const CHUNK_THRESHOLD = 0.42;  // Abaissé pour gérer les questions sans contexte

    // Diversité : deux chunks avec une similarité > ce seuil sont
    // considérés comme redondants → le 2e est écarté
    private const DIVERSITY_THRESHOLD = 0.85;  // Réduit pour permettre un peu plus de variété

    // Pourcentage des meilleurs chunks à retourner après scoring
    // On prend les 30% les plus pertinents pour optimiser la qualité/coût
    private const TOP_PERCENT = 0.30;  // 30%
    
    // Limite absolue de chunks (même si 30% dépasse cette valeur)
    private const MAX_CHUNKS = 10;  // Maximum absolu

    // Limite de contexte pour éviter d’envoyer un prompt inutilement lourd.
    private const MAX_CONTEXT_CHARS = 18000;

    public function __construct(
        private CohereEmbeddingService $embeddings,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // Point d'entrée
    // ═══════════════════════════════════════════════════════════════

    /**
     * Retourne le contexte textuel le plus pertinent pour répondre
     * à $question, dans la limite du projet $projetId si fourni.
     *
     * @return array{ context: string, source: string, faq: ?\App\Models\Faq }
     *   source = 'faq' | 'chunks' | 'fallback'
     *   faq    = le modèle Faq correspondant si source === 'faq', sinon null
     */
    public function retrieve(string $question, ?int $projetId = null): array
    {
        // ── Étape 1 : embedding de la question ───────────────────
        if (!$this->embeddings->isConfigured()) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null, 'faq_context' => ''];
        }

        // Une question doit utiliser input_type=search_query, différent du
        // mode search_document utilisé lors de l’indexation.
        $questionVector = $this->embeddings->embedQuery($question);
        if ($questionVector === null) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null, 'faq_context' => ''];
        }

        // Une seule vectorisation de la question sert à la FAQ et aux chunks.
        $faqSearch = $this->searchFaqs($questionVector, $projetId);
        if ($faqSearch['match'] !== null) {
            $faqMatch = $faqSearch['match'];
            Log::debug('RetrievalService: réponse FAQ directe', ['score' => $faqMatch['score']]);
            return [
                'context'     => $faqMatch['context'],
                'source'      => 'faq',
                'faq'         => $faqMatch['faq'],
                'faq_context' => $faqSearch['context'],
            ];
        }

        // ── Étape 2 : similarité cosinus sur les chunks ──────────
        $chunks = $this->loadChunks($projetId);
        if ($chunks->isEmpty()) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null, 'faq_context' => $faqSearch['context']];
        }

        $scored = $this->scoreChunks($chunks, $questionVector);

        if (empty($scored)) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null, 'faq_context' => $faqSearch['context']];
        }

        // ── Étape 3 : déduplication sémantique (MMR simplifié) ───
        $selected = $this->deduplicate($scored);

        if (empty($selected)) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null, 'faq_context' => $faqSearch['context']];
        }

        $contextParts = [];
        $contextChars = 0;
        foreach ($selected as $item) {
            $content = trim((string) $item['chunk']->content);
            if ($content === '') continue;

            $separatorChars = empty($contextParts) ? 0 : 9;
            $remaining = self::MAX_CONTEXT_CHARS - $contextChars - $separatorChars;
            if ($remaining <= 0) break;

            $contextParts[] = mb_substr($content, 0, $remaining);
            $contextChars += mb_strlen($contextParts[array_key_last($contextParts)]) + $separatorChars;
        }
        $context = implode("\n\n---\n\n", $contextParts);

        Log::debug('RetrievalService: chunks sélectionnés', [
            'count'  => count($contextParts),
            'scores' => array_map(fn ($s) => round($s['score'], 3), $selected),
        ]);

        return [
            'context'      => $context,
            'source'       => 'chunks',
            'faq'          => null,
            'faq_context'  => $faqSearch['context'],
            'chunks_count' => count($contextParts),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Étape 1 — FAQ directe
    // ═══════════════════════════════════════════════════════════════

    private function searchFaqs(array $questionVector, ?int $projetId): array
    {
        $query = Faq::query();
        if ($projetId !== null) {
            // On filtre les FAQs liées aux documentations du projet
            $docIds = \App\Models\Documentation::where('projet_id', $projetId)->pluck('id');
            $query->whereIn('documentation_id', $docIds);
        }
        $faqs = $query->whereNotNull('embedding')->get();

        if ($faqs->isEmpty()) {
            return ['match' => null, 'context' => ''];
        }

        $ranked = [];

        foreach ($faqs as $faq) {
            $faqVector = is_array($faq->embedding) ? $faq->embedding : json_decode($faq->embedding, true);
            if (!is_array($faqVector)) {
                continue;
            }
            $score = CohereEmbeddingService::cosineSimilarity($questionVector, $faqVector);
            $ranked[] = ['faq' => $faq, 'score' => $score];
        }

        if (empty($ranked)) {
            return ['match' => null, 'context' => ''];
        }

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $ranked[0];
        $faqContext = [];
        foreach (array_slice($ranked, 0, self::MAX_FAQ_CONTEXT) as $item) {
            if ($item['score'] < self::FAQ_CONTEXT_THRESHOLD) continue;
            $faqContext[] = "Q : {$item['faq']->question}\nR : {$item['faq']->reponse}";
        }

        $context = implode("\n\n---\n\n", $faqContext);
        $direct = null;
        if ($best['score'] >= self::FAQ_THRESHOLD) {
            $direct = [
                'context' => "**FAQ — Réponse directe**\n\nQ : {$best['faq']->question}\nR : {$best['faq']->reponse}",
                'score'   => $best['score'],
                'faq'     => $best['faq'],
            ];
        }

        return ['match' => $direct, 'context' => $context];
    }

    // ═══════════════════════════════════════════════════════════════
    // Étape 3 — Scoring des chunks
    // ═══════════════════════════════════════════════════════════════

    private function loadChunks(?int $projetId)
    {
        $query = DocumentationChunk::whereNotNull('embedding');

        if ($projetId !== null) {
            $docIds = \App\Models\Documentation::whereHas('projet', fn ($q) => $q->where('id', $projetId))->pluck('id');
            $query->whereIn('documentation_id', $docIds);
        }

        return $query->get();
    }

    /**
     * Retourne les chunks avec un score ≥ CHUNK_THRESHOLD,
     * limités aux 30% les plus pertinents, triés par score décroissant.
     */
    private function scoreChunks($chunks, array $questionVector): array
    {
        $scored = [];

        foreach ($chunks as $chunk) {
            $vector = is_array($chunk->embedding) ? $chunk->embedding : json_decode($chunk->embedding, true);
            if (!is_array($vector)) {
                continue;
            }
            $score = CohereEmbeddingService::cosineSimilarity($questionVector, $vector);
            if ($score >= self::CHUNK_THRESHOLD) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        // Trier par score décroissant
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        
        // Prendre les 30% les plus pertinents (minimum 1, maximum MAX_CHUNKS)
        $total = count($scored);
        if ($total === 0) {
            return [];
        }
        
        $topCount = max(1, min((int)ceil($total * self::TOP_PERCENT), self::MAX_CHUNKS));
        
        Log::debug('RetrievalService: sélection des meilleurs chunks', [
            'total_above_threshold' => $total,
            'top_30_percent' => $topCount,
        ]);
        
        return array_slice($scored, 0, $topCount);
    }

    // ═══════════════════════════════════════════════════════════════
    // Étape 4 — Déduplication sémantique (Maximal Marginal Relevance)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Sélectionne jusqu'à MAX_CHUNKS chunks en écartant les redondants.
     *
     * Algorithme MMR simplifié :
     *  - On prend toujours le chunk avec le meilleur score.
     *  - Pour chaque chunk suivant, on calcule sa similarité avec tous
     *    les chunks déjà sélectionnés.
     *  - Si elle dépasse DIVERSITY_THRESHOLD avec l'un d'eux, on écarte
     *    le chunk (il est redondant avec un chunk déjà choisi).
     *  - Sinon, on l'ajoute à la sélection.
     *
     * Résultat : MAX_CHUNKS chunks diversifiés et pertinents.
     */
    private function deduplicate(array $scored): array
    {
        $selected    = [];
        $selectedVec = [];

        foreach ($scored as $item) {
            if (count($selected) >= self::MAX_CHUNKS) {
                break;
            }

            $vector = is_array($item['chunk']->embedding) ? $item['chunk']->embedding : json_decode($item['chunk']->embedding, true);

            // Premier chunk : toujours sélectionné
            if (empty($selected)) {
                $selected[]    = $item;
                $selectedVec[] = $vector;
                continue;
            }

            // Vérifier la similarité avec chaque chunk déjà sélectionné
            $redundant = false;
            foreach ($selectedVec as $sv) {
                if (CohereEmbeddingService::cosineSimilarity($vector, $sv) > self::DIVERSITY_THRESHOLD) {
                    $redundant = true;
                    break;
                }
            }

            if (!$redundant) {
                $selected[]    = $item;
                $selectedVec[] = $vector;
            }
        }

        return $selected;
    }

    // ═══════════════════════════════════════════════════════════════
    // Fallback — contenu brut de la documentation (optimisé)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fallback intelligent : au lieu d'envoyer 50k caractères arbitraires,
     * on envoie les 3 premiers chunks disponibles (même sans embeddings)
     * ou un extrait structuré du document.
     */
    private function fallback(?int $projetId): string
    {
        // Tentative 1 : récupérer les chunks existants (même sans embeddings)
        $query = DocumentationChunk::query();
        if ($projetId !== null) {
            $docIds = \App\Models\Documentation::whereHas('projet', fn ($q) => $q->where('id', $projetId))->pluck('id');
            $query->whereIn('documentation_id', $docIds);
        }
        
        $chunks = $query->orderBy('chunk_index')->limit(3)->get(['content']);
        
        if ($chunks->isNotEmpty()) {
            Log::debug('RetrievalService: fallback avec chunks existants', [
                'chunks_count' => $chunks->count(),
            ]);
            return $chunks->pluck('content')->join("\n\n---\n\n");
        }

        // Tentative 2 : extraire les 3 premières sections du contenu brut
        $query = \App\Models\Documentation::query();
        if ($projetId !== null) {
            $query->where('projet_id', $projetId);
        }
        $docs = $query->get(['contenu', 'titre']);

        if ($docs->isEmpty()) {
            return '';
        }

        $sections = [];
        foreach ($docs as $doc) {
            // Découper par titres Markdown ## ou par paragraphes
            $content = $doc->contenu;
            $parts = preg_split('/\n\s*#{1,3}\s+/', $content);
            
            if (count($parts) > 1) {
                // Document structuré en Markdown
                foreach (array_slice($parts, 0, 2) as $part) {
                    $cleaned = trim($part);
                    if (mb_strlen($cleaned) > 100) {
                        $sections[] = "[{$doc->titre}]\n" . mb_substr($cleaned, 0, 2000);
                    }
                }
            } else {
                // Document non structuré : prendre les premiers paragraphes
                $paragraphs = preg_split('/\n\s*\n/', $content);
                $excerpt = '';
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if (empty($p) || mb_strlen($p) < 50) continue;
                    $excerpt .= $p . "\n\n";
                    if (mb_strlen($excerpt) > 2000) break;
                }
                if (!empty($excerpt)) {
                    $sections[] = "[{$doc->titre}]\n" . trim($excerpt);
                }
            }
            
            // Limiter à 3 sections au total
            if (count($sections) >= 3) break;
        }

        Log::debug('RetrievalService: fallback avec extraction intelligente', [
            'sections_count' => count($sections),
        ]);

        return implode("\n\n---\n\n", array_slice($sections, 0, 3));
    }
}
