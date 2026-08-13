<?php

namespace App\Services;

use App\Models\Documentation;
use App\Models\DocumentationChunk;
use App\Models\AiUsage;
use Illuminate\Support\Facades\Log;

class DocumentationIndexer
{
    public function __construct(
        private ChunkingService        $chunker,
        private CohereEmbeddingService $embeddings,
    ) {}

    /**
     * (Re)génère les chunks + embeddings d'une documentation.
     *
     * Flux :
     *  1. ChunkingService demande à Gemini de segmenter le document
     *     par intention/sujet (fallback Markdown si Gemini indispo).
     *  2. Chaque chunk est ensuite vectorisé par Cohere pour la
     *     recherche par similarité.
     *
     * Les deux étapes sont indépendantes : si Cohere est absent,
     * les chunks sont quand même créés (sans embedding) et le
     * RetrievalService utilisera le fallback texte brut.
     */
    public function index(Documentation $documentation): void
    {
        $documentation->loadMissing('projet');
        DocumentationChunk::where('documentation_id', $documentation->id)->delete();

        $chunksText = $this->chunker->split($documentation->contenu);

        if (empty($chunksText)) {
            Log::warning('DocumentationIndexer: aucun chunk généré', [
                'documentation_id' => $documentation->id,
            ]);
            return;
        }

        Log::info('DocumentationIndexer: chunks générés par Gemini', [
            'documentation_id' => $documentation->id,
            'nb_chunks'        => count($chunksText),
        ]);

        // Préfixer chaque chunk par le titre du document pour aider
        // la recherche sémantique Cohere
        $textsToEmbed = array_map(
            fn ($chunk) => "[{$documentation->titre}]\n{$chunk}",
            $chunksText
        );

        // Vectorisation Cohere (peut être null si clé absente)
        $vectors = $this->embeddings->isConfigured()
            ? $this->embeddings->embedBatch($textsToEmbed)
            : array_fill(0, count($chunksText), null);
        $embeddingTokens = $this->embeddings->lastUsageTokens();

        foreach ($chunksText as $i => $chunkText) {
            DocumentationChunk::create([
                'documentation_id' => $documentation->id,
                'chunk_index'      => $i,
                'content'          => $chunkText,
                'embedding'        => $vectors[$i] ?? null,
            ]);
        }

        $withEmbedding = count(array_filter($vectors));
        Log::info('DocumentationIndexer: indexation terminée', [
            'documentation_id'   => $documentation->id,
            'chunks_total'       => count($chunksText),
            'chunks_vectorisés'  => $withEmbedding,
        ]);

        AiUsage::recordUsage([
            'client_id'        => $documentation->projet?->client_id,
            'request_type'     => 'embedding_index',
            'provider'         => 'cohere',
            'model'            => 'embed-multilingual-v3.0',
            'tokens_input'     => 0,
            'tokens_output'    => 0,
            'embedding_tokens' => $embeddingTokens,
            'metadata'         => [
                'documentation_id' => $documentation->id,
                'chunks_total'     => count($chunksText),
                'chunks_vectorized'=> $withEmbedding,
            ],
        ]);
    }
}
