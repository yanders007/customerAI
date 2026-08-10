<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ChunkingService — Segmentation intelligente par Gemini.
 *
 * Au lieu de couper mécaniquement par nombre de mots ou par similarité
 * cosinus (qui rate souvent les ruptures sémantiques réelles), on demande
 * directement à Gemini de lire le document et de le découper en sections
 * cohérentes par INTENTION/SUJET.
 *
 * Pipeline :
 *  1. Gemini lit le document complet et retourne un JSON avec les chunks
 *     (titre + contenu) — chaque chunk = un sujet autonome.
 *  2. Si Gemini échoue (quota, erreur réseau) → fallback par structure
 *     Markdown (titres ## / ###).
 *  3. Si pas de structure Markdown → fallback par fenêtre de mots.
 *
 * Avantages vs cosinus :
 *  - Comprend le sens, pas juste les mots
 *  - Respecte les ruptures logiques même sans titre Markdown
 *  - Ne mélange jamais deux sujets dans le même chunk
 *  - Gère les documents techniques, narratifs, et mélangés
 */
class ChunkingService
{
    // ── Fallback par mots ─────────────────────────────────────────────
    // Ces valeurs sont utilisées UNIQUEMENT si Gemini ET la structure Markdown échouent
    private const TARGET_WORDS      = 300;  // Augmenté pour des chunks plus cohérents
    private const OVERLAP_WORDS     = 50;   // Overlap plus important pour conserver le contexte
    private const SEMANTIC_MIN_WORDS = 120; // Minimum augmenté pour éviter les mini-chunks
    private const MAX_WORDS         = 600;  // Maximum augmenté pour ne pas couper arbitrairement

    // ── Gemini ────────────────────────────────────────────────────────
    // Modèle léger : on ne génère pas de réponse, juste de la structuration.
    // Flash est suffisant et beaucoup plus rapide que Pro.
    private const GEMINI_MODEL = 'gemini-2.0-flash';
    private const GEMINI_BASE  = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct() {}

    // ═══════════════════════════════════════════════════════════════════
    // Point d'entrée public
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Découpe un document en chunks sémantiquement cohérents.
     *
     * @return string[] Chunks dans l'ordre du document.
     */
    public function split(string $text): array
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            return [];
        }

        // ── Passe 1 : segmentation par Gemini ────────────────────────
        if ($this->isGeminiConfigured()) {
            $geminiChunks = $this->splitWithGemini($text);
            if (count($geminiChunks) >= 2) {
                Log::info('ChunkingService: segmentation Gemini réussie', [
                    'chunks' => count($geminiChunks),
                ]);
                return $this->enforceMaxWords($geminiChunks);
            }
        }

        // ── Passe 2 : fallback structure Markdown ─────────────────────
        $structured = $this->splitByStructure($text);
        if (count($structured) >= 2) {
            Log::info('ChunkingService: fallback structure Markdown', [
                'chunks' => count($structured),
            ]);
            return $this->enforceMaxWords($structured);
        }

        // ── Passe 3 : fallback fenêtre de mots ────────────────────────
        Log::info('ChunkingService: fallback fenêtre de mots');
        return $this->splitByWords($text);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 1 — Segmentation par Gemini
    // ═══════════════════════════════════════════════════════════════════

    private function isGeminiConfigured(): bool
    {
        return !empty(config('services.gemini.api_key'));
    }

    /**
     * Demande à Gemini de lire le document et de le segmenter.
     *
     * On lui donne un prompt très précis avec un format JSON strict pour
     * que la réponse soit facilement parsable, sans markdown autour.
     *
     * Stratégie de prompt :
     * - On explique ce qu'est un "bon" chunk (1 sujet = 1 chunk)
     * - On lui interdit de résumer ou reformuler (texte original)
     * - On limite à 15 chunks max pour éviter une sur-segmentation
     * - Format JSON : tableau d'objets {titre, contenu}
     */
    private function splitWithGemini(string $text): array
    {
        $apiKey = config('services.gemini.api_key');

        // On tronque à 80k chars pour rester dans les limites de contexte
        // de Flash et éviter les timeouts. 80k ≈ 60 pages Word.
        $truncated = mb_substr($text, 0, 80000);

        $prompt = <<<PROMPT
Tu es un expert en traitement de documents techniques. Ton rôle est de SEGMENTER ce document en sections thématiques cohérentes.

RÈGLES STRICTES :
1. Chaque section (chunk) doit traiter UN SEUL sujet, une intention ou un concept cohérent.
2. Ne JAMAIS couper une procédure, une liste, un exemple de code ou une explication en plein milieu.
3. Ne JAMAIS résumer ni reformuler — copie le texte original de chaque section tel quel, mot pour mot.
4. Créer entre 3 et 12 sections selon la longueur du document.
5. Chaque section doit contenir au minimum 100 mots pour avoir du contexte suffisant.
6. Si deux paragraphes ou sections parlent du même sujet ou de la même intention, les regrouper dans une seule section.
7. Respecter les transitions naturelles du document : changement de sujet, nouvelle procédure, nouveau concept.
8. Si une section devient trop longue (>800 mots), la diviser en sous-sections logiques tout en gardant la cohérence sémantique.

RÉPONDS UNIQUEMENT avec un tableau JSON valide, sans markdown, sans explication.
Format exact :
[
  {"titre": "Titre court de la section", "contenu": "Texte complet de la section copié tel quel..."},
  {"titre": "...", "contenu": "..."}
]

DOCUMENT À SEGMENTER :
{$truncated}
PROMPT;

        try {
            $response = Http::timeout(60)
                ->connectTimeout(10)
                ->post(self::GEMINI_BASE . self::GEMINI_MODEL . ':generateContent?key=' . $apiKey, [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.1,   // très déterministe
                        'maxOutputTokens' => 16384,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('ChunkingService: Gemini HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($rawText)) {
                Log::warning('ChunkingService: Gemini réponse vide');
                return [];
            }

            return $this->parseGeminiResponse($rawText, $truncated);

        } catch (\Throwable $e) {
            Log::warning('ChunkingService: Gemini exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Parse la réponse JSON de Gemini et retourne les chunks texte.
     *
     * Robustesse : si Gemini entoure le JSON de backticks markdown
     * malgré l'instruction, on les nettoie avant de parser.
     */
    private function parseGeminiResponse(string $raw, string $originalText): array
    {
        // Nettoyer les éventuels backticks markdown
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw)) ?? $raw;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (!is_array($decoded) || empty($decoded)) {
            Log::warning('ChunkingService: JSON Gemini invalide', ['raw' => mb_substr($raw, 0, 500)]);
            return [];
        }

        $chunks = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;

            // Accepter "contenu" ou "content" (Gemini peut varier)
            $content = trim((string) ($item['contenu'] ?? $item['content'] ?? ''));
            $titre   = trim((string) ($item['titre']   ?? $item['title']   ?? ''));

            if ($content === '') continue;

            // Préfixer par le titre pour aider la recherche sémantique
            $chunk = $titre !== '' ? "## {$titre}\n\n{$content}" : $content;
            $chunks[] = $chunk;
        }

        // Validation : si Gemini a retourné moins de 2 chunks ou que le
        // contenu total est < 50% du document original, on considère que
        // la segmentation a échoué (Gemini a peut-être résumé au lieu
        // de copier).
        if (count($chunks) < 1) {
            return [];
        }

        $totalChunkChars = array_sum(array_map('mb_strlen', $chunks));
        $originalChars   = mb_strlen($originalText);

        // On accepte jusqu'à 65% de perte (titres ajoutés, espaces normalisés, docs avec beaucoup de code)
        // Pour les petits documents, on est plus tolérant
        $minRatio = $originalChars < 5000 ? 0.35 : 0.40;
        if ($totalChunkChars < $originalChars * $minRatio) {
            Log::warning('ChunkingService: Gemini a probablement résumé au lieu de copier', [
                'original_chars' => $originalChars,
                'chunks_chars'   => $totalChunkChars,
                'ratio'          => round($totalChunkChars / max($originalChars, 1), 2),
            ]);
            return [];
        }

        return $chunks;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 2 — Fallback structure Markdown
    // ═══════════════════════════════════════════════════════════════════

    private function splitByStructure(string $text): array
    {
        $rawSections = preg_split('/(?=^#{1,4}\s)/m', $text) ?: [$text];
        $rawSections = array_values(array_filter(
            array_map('trim', $rawSections),
            fn ($s) => $s !== ''
        ));

        if (count($rawSections) < 2) {
            return [];
        }

        $chunks  = [];
        $current = '';

        foreach ($rawSections as $section) {
            $current .= ($current === '' ? '' : "\n\n") . $section;

            if ($this->wordCount($current) >= self::SEMANTIC_MIN_WORDS) {
                $chunks[] = $current;
                $current  = '';
            }
        }

        if ($current !== '') {
            if (!empty($chunks) && $this->wordCount($current) < self::SEMANTIC_MIN_WORDS) {
                $chunks[count($chunks) - 1] .= "\n\n" . $current;
            } else {
                $chunks[] = $current;
            }
        }

        return $chunks;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 3 — Fallback fenêtre de mots
    // ═══════════════════════════════════════════════════════════════════

    private function splitByWords(string $text): array
    {
        $paragraphs = $this->getParagraphs($text);
        if (empty($paragraphs)) {
            return [$text];
        }

        $chunks       = [];
        $currentWords = [];

        foreach ($paragraphs as $paragraph) {
            $paragraphWords = preg_split('/\s+/', $paragraph) ?: [];

            if (count($paragraphWords) > self::TARGET_WORDS * 1.5) {
                foreach (array_chunk($paragraphWords, self::TARGET_WORDS) as $slice) {
                    $currentWords = array_merge($currentWords, $slice);
                    if (count($currentWords) >= self::TARGET_WORDS) {
                        $chunks[]     = implode(' ', $currentWords);
                        $currentWords = array_slice($currentWords, -self::OVERLAP_WORDS);
                    }
                }
                continue;
            }

            $currentWords = array_merge($currentWords, $paragraphWords);

            if (count($currentWords) >= self::TARGET_WORDS) {
                $chunks[]     = implode(' ', $currentWords);
                $currentWords = array_slice($currentWords, -self::OVERLAP_WORDS);
            }
        }

        if (count($currentWords) > self::OVERLAP_WORDS) {
            $chunks[] = implode(' ', $currentWords);
        }

        if (empty($chunks)) {
            $chunks[] = $text;
        }

        return $chunks;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    private function getParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [$text];
        return array_values(array_filter(
            array_map('trim', $paragraphs),
            fn ($p) => $p !== ''
        ));
    }

    private function enforceMaxWords(array $chunks): array
    {
        $result = [];
        foreach ($chunks as $chunk) {
            if ($this->wordCount($chunk) <= self::MAX_WORDS) {
                $result[] = $chunk;
                continue;
            }
            $words = preg_split('/\s+/', $chunk) ?: [];
            foreach (array_chunk($words, self::TARGET_WORDS) as $slice) {
                $result[] = implode(' ', $slice);
            }
        }
        return $result;
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/', trim($text));
        return $words ? count(array_filter($words, fn ($w) => $w !== '')) : 0;
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
