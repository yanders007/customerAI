<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;

class PurgeConversations extends Command
{
    protected $signature = 'conversations:purge {--days= : Durée de rétention : 2 ou 3 jours}';

    protected $description = 'Supprime les conversations plus anciennes que la durée de rétention configurée';

    public function handle(): int
    {
        $requestedDays = (int) ($this->option('days') ?: config('services.conversations.retention_days', 3));
        $days = in_array($requestedDays, [2, 3], true) ? $requestedDays : 3;
        $cutoff = now()->subDays($days);

        $deleted = Conversation::where('created_at', '<', $cutoff)->delete();

        $this->info("{$deleted} conversation(s) supprimée(s) avant {$cutoff->toDateTimeString()}.");
        return self::SUCCESS;
    }
}
