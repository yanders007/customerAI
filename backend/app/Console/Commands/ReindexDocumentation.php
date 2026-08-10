<?php

namespace App\Console\Commands;

use App\Models\Documentation;
use App\Services\DocumentationIndexer;
use Illuminate\Console\Command;

class ReindexDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:reindex {--id= : ID spécifique d\'un document à réindexer} {--delay=2 : Délai en secondes entre chaque document pour éviter le rate limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réindexe la documentation pour générer/regénérer les embeddings';

    /**
     * Execute the console command.
     */
    public function handle(DocumentationIndexer $indexer)
    {
        $documentId = $this->option('id');
        $delay = (int) $this->option('delay');

        if ($documentId) {
            // Réindexation d'un document spécifique
            $doc = Documentation::find($documentId);
            if (!$doc) {
                $this->error("Document ID {$documentId} introuvable.");
                return 1;
            }

            $this->info("Réindexation du document : {$doc->titre}");
            $indexer->index($doc);

            $chunksCount = $doc->chunks()->count();
            $withEmbedding = $doc->chunks()->whereNotNull('embedding')->count();

            $this->info("✓ Terminé : {$chunksCount} chunks créés, {$withEmbedding} avec embeddings");

            if ($withEmbedding === 0 && $chunksCount > 0) {
                $this->warn("⚠ Aucun embedding généré. Vérifiez les logs et votre quota API Gemini.");
            }

            return 0;
        }

        // Réindexation de tous les documents
        $docs = Documentation::all();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('Aucune documentation à réindexer.');
            return 0;
        }

        $this->info("Réindexation de {$total} document(s)...");
        $bar = $this->output->createProgressBar($total);

        $success = 0;
        $errors = 0;

        foreach ($docs as $doc) {
            try {
                $indexer->index($doc);
                $chunksCount = $doc->chunks()->count();
                $withEmbedding = $doc->chunks()->whereNotNull('embedding')->count();

                if ($withEmbedding > 0 || $chunksCount === 0) {
                    $success++;
                } else {
                    $errors++;
                }

                $bar->advance();

                // Pause pour éviter le rate limit de l'API Gemini
                if ($delay > 0 && !$docs->last()->is($doc)) {
                    sleep($delay);
                }
            } catch (\Throwable $e) {
                $this->error("\n✗ Erreur pour {$doc->titre}: {$e->getMessage()}");
                $errors++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Réindexation terminée : {$success} succès, {$errors} erreurs");

        if ($errors > 0) {
            $this->warn("⚠ Certains embeddings n'ont pas pu être générés. Vérifiez :");
            $this->warn("  - Votre clé API Gemini dans .env (GEMINI_API_KEY)");
            $this->warn("  - Votre quota API : https://aistudio.google.com/apikey");
            $this->warn("  - Les logs : tail -f storage/logs/laravel.log");
        }

        return $errors > 0 ? 1 : 0;
    }
}
