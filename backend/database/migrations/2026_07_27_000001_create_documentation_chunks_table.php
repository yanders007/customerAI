<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Découpage vectorisé des documentations (RAG) : chaque
        // documentation longue est découpée en petits morceaux, chacun
        // associé à son "embedding" (empreinte numérique du sens du
        // texte). À chaque question, on ne renvoie à l'IA que les
        // morceaux les plus pertinents au lieu de toute la documentation
        // — moins de tokens, plus rapide, et ça passe même pour des
        // documents très longs (manuels techniques, livres entiers...).
        Schema::create('documentation_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documentation_id')->constrained('documentations')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            // Vecteur d'embedding stocké en JSON (tableau de floats).
            // Pas besoin d'une extension vectorielle dédiée (pgvector...)
            // à cette échelle : quelques dizaines/centaines de chunks par
            // projet se comparent très bien en PHP.
            $table->json('embedding')->nullable();
            $table->timestamps();
            $table->index('documentation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_chunks');
    }
};
