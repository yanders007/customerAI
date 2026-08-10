<?php

namespace App\Jobs;

use App\Models\Documentation;
use App\Services\DocumentationIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexDocumentationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Nombre de tentatives en cas d'échec (rate limit, etc.)
     */
    public $tries = 3;

    /**
     * Délai en secondes avant une nouvelle tentative
     */
    public $backoff = [30, 60, 120];

    /**
     * Timeout de 5 minutes (pour les gros documents)
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Documentation $documentation
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentationIndexer $indexer): void
    {
        Log::info("IndexDocumentationJob: début indexation", [
            'documentation_id' => $this->documentation->id,
            'titre' => $this->documentation->titre,
        ]);

        try {
            $indexer->index($this->documentation);

            $chunksCount = $this->documentation->chunks()->count();
            $withEmbedding = $this->documentation->chunks()->whereNotNull('embedding')->count();

            Log::info("IndexDocumentationJob: indexation terminée", [
                'documentation_id' => $this->documentation->id,
                'chunks' => $chunksCount,
                'embeddings' => $withEmbedding,
            ]);

            // Si aucun embedding n'a été généré mais qu'il y a des chunks,
            // c'est probablement un quota dépassé. Pour les gros documents
            // (>40 chunks), on recommande l'utilisation de docs:reindex
            // avec un délai plus long, car le retry échouera probablement aussi.
            if ($chunksCount > 0 && $withEmbedding === 0) {
                if ($chunksCount > 40) {
                    Log::warning("IndexDocumentationJob: gros document sans embeddings - utiliser docs:reindex", [
                        'documentation_id' => $this->documentation->id,
                        'chunks' => $chunksCount,
                        'recommandation' => "php artisan docs:reindex --id={$this->documentation->id} --delay=5",
                    ]);
                    // Ne pas retry pour les gros documents, ça échouera encore
                    $this->fail(new \Exception("Document trop volumineux ({$chunksCount} chunks) - Utilisez: php artisan docs:reindex --id={$this->documentation->id} --delay=5"));
                } else {
                    Log::warning("IndexDocumentationJob: aucun embedding généré, retry", [
                        'documentation_id' => $this->documentation->id,
                        'attempt' => $this->attempts(),
                    ]);
                    
                    // Relancer le job pour les petits documents
                    if ($this->attempts() < $this->tries) {
                        $this->release(60); // Réessayer dans 60 secondes
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("IndexDocumentationJob: erreur indexation", [
                'documentation_id' => $this->documentation->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Relancer automatiquement selon la stratégie de backoff
            throw $e;
        }
    }

    /**
     * Gestion de l'échec définitif du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("IndexDocumentationJob: échec définitif", [
            'documentation_id' => $this->documentation->id,
            'titre' => $this->documentation->titre,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Envoyer une notification à l'administrateur
        // Mail::to(config('support.email'))->send(new IndexationFailedMail($this->documentation));
    }
}
