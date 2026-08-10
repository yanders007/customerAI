<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private const MODEL = 'gemini-embedding-001';

    // Nombre max de textes envoyés en une seule requête batch à l'API.
    // Google limite les requêtes batch ; 90 est une valeur prudente.
    private const BATCH_SIZE = 90;

    public function isConfigured(): bool
    {
        return !empty(config('services.gemini.api_key'));
    }

    /**
     * Calcule l'embedding d'un seul texte (typiquement : la question du
     * client). Retourne null si l'appel échoue (clé absente, quota,
     * réseau...) — l'appelant doit alors savoir se rabattre sur un
     * comportement sans RAG plutôt que de planter.
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
     * Inclut un délai de 2 secondes entre chaque batch pour respecter
     * le rate limit de l'API Gemini gratuite (15 RPM).
     *
     * @param string[] $texts
     * @return array<int, array<float>|null>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts) || !$this->isConfigured()) {
            return array_fill(0, count($texts), null);
        }

        $apiKey  = config('services.gemini.api_key');
        $results = [];

        foreach (array_chunk($texts, self::BATCH_SIZE, true) as $batchIndex => $batch) {
            // Délai entre les batches pour respecter le rate limit (15 RPM)
            // Sauf pour le premier batch (pas de délai initial)
            if ($batchIndex > 0) {
                sleep(2);
            }

            $requests = array_map(fn ($text) => [
                'model'   => 'models/' . self::MODEL,
                'content' => ['parts' => [['text' => mb_substr($text, 0, 8000)]]],
            ], $batch);

            try {
                $response = Http::timeout(60)
                    ->asJson()
                    ->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL . ":batchEmbedContents?key={$apiKey}",
                        ['requests' => array_values($requests)]
                    );

                if (!$response->successful()) {
                    Log::warning('EmbeddingService: échec appel Gemini embeddings', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    foreach ($batch as $index => $text) {
                        $results[$index] = null;
                    }
                    continue;
                }

                $embeddings = $response->json('embeddings') ?? [];
                $i = 0;
                foreach ($batch as $index => $text) {
                    $results[$index] = $embeddings[$i]['values'] ?? null;
                    $i++;
                }
            } catch (\Throwable $e) {
                Log::warning('EmbeddingService: exception lors de la vectorisation', ['error' => $e->getMessage()]);
                foreach ($batch as $index => $text) {
                    $results[$index] = null;
                }
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Similarité cosinus entre deux vecteurs : proche de 1 = sens très
     * proche, proche de 0 = sens sans rapport. C'est cette mesure qui
     * permet de retrouver "quel passage de la doc parle de la même
     * chose que la question", même avec des mots complètement différents.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}