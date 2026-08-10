<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CohereEmbeddingService
{
    // Modèle Cohere multilingue optimisé pour la recherche sémantique
    private const MODEL = 'embed-multilingual-v3.0';

    // Nombre max de textes par requête. Cohere accepte jusqu'à 96.
    private const BATCH_SIZE = 90;

    // Délai entre les batches pour respecter le rate limit (20 RPM)
    // 3 secondes = safe pour 20 requêtes/minute
    private const BATCH_DELAY_SECONDS = 3;

    public function isConfigured(): bool
    {
        return !empty(config('services.cohere.api_key'));
    }

    /**
     * Calcule l'embedding d'un seul texte (typiquement : la question du
     * client). Retourne null si l'appel échoue.
     */
    public function embed(string $text): ?array
    {
        $results = $this->embedBatch([$text]);
        return $results[0] ?? null;
    }

    /**
     * Calcule les embeddings d'une liste de textes en un minimum
     * d'appels API. Retourne un tableau de même taille que $texts,
     * chaque entrée étant soit le vecteur (array de floats), soit null
     * si ce texte précis n'a pas pu être vectorisé.
     *
     * Cohere retourne des vecteurs de 1024 dimensions (vs 3072 pour Gemini).
     * Plus compact = plus rapide et moins de stockage.
     *
     * @param string[] $texts
     * @return array<int, array<float>|null>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts) || !$this->isConfigured()) {
            return array_fill(0, count($texts), null);
        }

        $apiKey = config('services.cohere.api_key');
        $results = [];

        foreach (array_chunk($texts, self::BATCH_SIZE, true) as $batchIndex => $batch) {
            // Délai entre les batches pour respecter le rate limit (20 RPM)
            if ($batchIndex > 0) {
                sleep(self::BATCH_DELAY_SECONDS);
            }

            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.cohere.ai/v1/embed', [
                        'model' => self::MODEL,
                        'texts' => array_values(array_map(
                            fn($text) => mb_substr($text, 0, 8000),
                            $batch
                        )),
                        'input_type' => 'search_document', // Pour l'indexation
                        'truncate' => 'END',
                    ]);

                if (!$response->successful()) {
                    Log::warning('CohereEmbeddingService: échec appel Cohere embeddings', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    foreach ($batch as $index => $text) {
                        $results[$index] = null;
                    }
                    continue;
                }

                $embeddings = $response->json('embeddings') ?? [];
                $i = 0;
                foreach ($batch as $index => $text) {
                    $results[$index] = $embeddings[$i] ?? null;
                    $i++;
                }
            } catch (\Throwable $e) {
                Log::warning('CohereEmbeddingService: exception lors de la vectorisation', [
                    'error' => $e->getMessage(),
                ]);
                foreach ($batch as $index => $text) {
                    $results[$index] = null;
                }
            }
        }

        ksort($results);
        return array_values($results);
    }

    /**
     * Calcule l'embedding d'une requête de recherche.
     * Utilise input_type='search_query' pour optimiser la recherche.
     */
    public function embedQuery(string $query): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $apiKey = config('services.cohere.api_key');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.cohere.ai/v1/embed', [
                    'model' => self::MODEL,
                    'texts' => [mb_substr($query, 0, 8000)],
                    'input_type' => 'search_query', // Optimisé pour les requêtes
                    'truncate' => 'END',
                ]);

            if (!$response->successful()) {
                Log::warning('CohereEmbeddingService: échec embedding query', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $embeddings = $response->json('embeddings') ?? [];
            return $embeddings[0] ?? null;
        } catch (\Throwable $e) {
            Log::warning('CohereEmbeddingService: exception embedding query', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Similarité cosinus entre deux vecteurs.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
