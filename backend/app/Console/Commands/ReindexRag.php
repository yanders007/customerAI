<?php

namespace App\Console\Commands;

use App\Models\Documentation;
use App\Models\Faq;
use App\Services\CohereEmbeddingService;
use App\Services\DocumentationIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReindexRag extends Command
{
    protected $signature = 'rag:reindex {--force : Rejouer l’indexation même si la version est déjà marquée comme terminée} {--delay=1 : Délai entre les embeddings FAQ}';

    protected $description = 'Réindexe automatiquement les documents et FAQ existants une seule fois par version RAG';

    private const MARKER = 'customerai.rag.reindex.v2';

    public function handle(DocumentationIndexer $indexer, CohereEmbeddingService $embeddings): int
    {
        if (!$embeddings->isConfigured()) {
            $this->warn('COHERE_API_KEY absente : l’indexation automatique sera retentée au prochain démarrage.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && Cache::has(self::MARKER)) {
            $this->info('Réindexation RAG déjà effectuée pour cette version.');
            return self::SUCCESS;
        }

        $errors = 0;
        $documents = Documentation::query()->orderBy('id')->get();

        foreach ($documents as $documentation) {
            try {
                $indexer->index($documentation);
                $chunks = $documentation->chunks()->count();
                $embeddingsCount = $documentation->chunks()->whereNotNull('embedding')->count();

                if ($chunks > 0 && $embeddingsCount === 0) {
                    $errors++;
                    $this->warn("Document {$documentation->id} sans embedding.");
                }
            } catch (\Throwable $e) {
                $errors++;
                report($e);
                $this->error("Erreur d’indexation du document {$documentation->id} : {$e->getMessage()}");
            }
        }

        $delay = max(0, (int) $this->option('delay'));
        $faqs = Faq::query()->select(['id', 'question'])->orderBy('id')->get();

        foreach ($faqs as $faq) {
            $vector = $embeddings->embedQuery($faq->question);
            if ($vector === null) {
                $errors++;
            } else {
                $faq->update(['embedding' => json_encode($vector)]);
            }

            if ($delay > 0 && !$faqs->last()->is($faq)) {
                sleep($delay);
            }
        }

        if ($errors > 0) {
            $this->warn("Réindexation RAG incomplète : {$errors} erreur(s). Le prochain démarrage réessaiera automatiquement.");
            return self::FAILURE;
        }

        Cache::forever(self::MARKER, [
            'completed_at' => now()->toIso8601String(),
            'documents'    => $documents->count(),
            'faqs'         => $faqs->count(),
        ]);

        $this->info("Réindexation RAG terminée : {$documents->count()} document(s), {$faqs->count()} FAQ.");
        return self::SUCCESS;
    }
}
