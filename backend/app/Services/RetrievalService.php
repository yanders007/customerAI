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
    private const FAQ_THRESHOLD = 0.75;  // Augmenté pour éviter les faux positifs

    // Chunks : score minimum pour inclure un chunk dans le contexte
    // Seuil calibré après tests : 0.42 pour capturer les questions courtes/vagues
    private const CHUNK_THRESHOLD = 0.42;  // Abaissé pour gérer les questions sans contexte

    // Diversité : deux chunks avec une similarité > ce seuil sont
    // considérés comme redondants → le 2e est écarté
    private const DIVERSITY_THRESHOLD = 0.85;  // Réduit pour permettre un peu plus de variété

    // Nombre maximum de chunks retournés après déduplication
    // Augmenté pour capturer plus de contexte et éviter de rater des infos importantes
    private const MAX_CHUNKS = 5;  // Augmenté de 3 à 5 pour meilleure couverture

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
        // ── Étape 1 : correspondance FAQ directe (sémantique) ────
        $faqMatch = $this->matchFaq($question, $projetId);
        if ($faqMatch !== null) {
            Log::debug('RetrievalService: réponse FAQ directe', ['score' => $faqMatch['score']]);
            return ['context' => $faqMatch['context'], 'source' => 'faq', 'faq' => $faqMatch['faq']];
        }

        // ── Étape 2 : embedding de la question ───────────────────
        if (!$this->embeddings->isConfigured()) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null];
        }

        $questionVector = $this->embeddings->embed($question);
        if ($questionVector === null) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null];
        }

        // ── Étape 3 : similarité cosinus sur les chunks ──────────
        $chunks = $this->loadChunks($projetId);
        if ($chunks->isEmpty()) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null];
        }

        $scored = $this->scoreChunks($chunks, $questionVector);

        if (empty($scored)) {
    return ['context' => $this->fallback($projetId), 'source' => 'fallback'];
}

        // ── Étape 4 : déduplication sémantique (MMR simplifié) ───
        $selected = $this->deduplicate($scored);

        if (empty($selected)) {
            return ['context' => $this->fallback($projetId), 'source' => 'fallback', 'faq' => null];
        }

        $context = implode("\n\n---\n\n", array_map(fn ($s) => $s['chunk']->content, $selected));

        Log::debug('RetrievalService: chunks sélectionnés', [
            'count'  => count($selected),
            'scores' => array_map(fn ($s) => round($s['score'], 3), $selected),
        ]);

        return ['context' => $context, 'source' => 'chunks', 'faq' => null, 'chunks_count' => count($selected)];
    }

    // ═══════════════════════════════════════════════════════════════
    // Étape 1 — FAQ directe
    // ═══════════════════════════════════════════════════════════════

    private function matchFaq(string $question, ?int $projetId): ?array
    {
        if (!$this->embeddings->isConfigured()) {
            return null;
        }

        $query = Faq::query();
        if ($projetId !== null) {
            // On filtre les FAQs liées aux documentations du projet
            $docIds = \App\Models\Documentation::where('projet_id', $projetId)->pluck('id');
            $query->whereIn('documentation_id', $docIds);
        }
        $faqs = $query->whereNotNull('embedding')->get();

        if ($faqs->isEmpty()) {
            return null;
        }

        $questionVector = $this->embeddings->embed($question);
        if ($questionVector === null) {
            return null;
        }

        $best      = null;
        $bestScore = 0.0;

        foreach ($faqs as $faq) {
            $faqVector = is_array($faq->embedding) ? $faq->embedding : json_decode($faq->embedding, true);
            if (!is_array($faqVector)) {
                continue;
            }
            $score = CohereEmbeddingService::cosineSimilarity($questionVector, $faqVector);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $faq;
            }
        }

        if ($best === null || $bestScore < self::FAQ_THRESHOLD) {
            return null;
        }

        $context = "**FAQ — Réponse directe**\n\nQ : {$best->question}\nR : {$best->reponse}";
        return ['context' => $context, 'score' => $bestScore, 'faq' => $best];
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
     * triés par score décroissant.
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

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
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
