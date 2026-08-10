<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            // Vecteur d'embedding Cohere pour la recherche sémantique directe
            // Stocké en JSON (tableau de flottants 1024 dimensions)
            $table->longText('embedding')->nullable()->after('reponse');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
