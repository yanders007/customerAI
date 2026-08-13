<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Services\CohereEmbeddingService;
use Illuminate\Console\Command;

class ReindexFaqs extends Command
{
    protected $signature = 'faqs:reindex {--delay=0 : Délai en secondes entre les embeddings}';

    protected $description = 'Régénère les embeddings des FAQ avec le mode search_query';

    public function handle(CohereEmbeddingService $embeddings): int
    {
        if (!$embeddings->isConfigured()) {
            $this->error('COHERE_API_KEY est absente ou invalide.');
            return self::FAILURE;
        }

        $faqs = Faq::query()->select(['id', 'question'])->orderBy('id')->get();
        if ($faqs->isEmpty()) {
            $this->info('Aucune FAQ à réindexer.');
            return self::SUCCESS;
        }

        $delay = max(0, (int) $this->option('delay'));
        $bar = $this->output->createProgressBar($faqs->count());
        $errors = 0;

        foreach ($faqs as $faq) {
            $vector = $embeddings->embedQuery($faq->question);
            if ($vector === null) {
                $errors++;
            } else {
                $faq->update(['embedding' => json_encode($vector)]);
            }

            $bar->advance();
            if ($delay > 0 && !$faqs->last()->is($faq)) {
                sleep($delay);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf(
            'Réindexation FAQ terminée : %d succès, %d erreurs.',
            $faqs->count() - $errors,
            $errors,
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
