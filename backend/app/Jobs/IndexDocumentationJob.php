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
            // le document ne doit pas être considéré comme indexé : le RAG
            // tomberait sinon silencieusement en fallback texte.
            if ($chunksCount > 0 && $withEmbedding === 0) {
                $message = "Aucun embedding Cohere généré pour le document {$this->documentation->id}. Vérifiez COHERE_API_KEY et le quota Cohere.";

                if ($chunksCount > 40) {
                    Log::warning("IndexDocumentationJob: document sans embeddings", [
                        'documentation_id' => $this->documentation->id,
                        'chunks' => $chunksCount,
                        'recommandation' => "php artisan docs:reindex --id={$this->documentation->id} --delay=5",
                    ]);
                    // Ne pas retry automatiquement les gros documents si la
                    // vectorisation est indisponible : l’échec doit être visible.
                    throw new \RuntimeException($message . " Réessayez avec docs:reindex --id={$this->documentation->id} --delay=5.");
                } else {
                    Log::warning("IndexDocumentationJob: aucun embedding généré", [
                        'documentation_id' => $this->documentation->id,
                        'attempt' => $this->attempts(),
                    ]);

                    if ($this->attempts() < $this->tries) {
                        $this->release(60); // Réessayer dans 60 secondes
                        return;
                    }

                    throw new \RuntimeException($message);
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
