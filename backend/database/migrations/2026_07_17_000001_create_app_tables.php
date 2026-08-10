<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Admins ───────────────────────────────────────────
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Clients ──────────────────────────────────────────
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('client_identifier', 50)->unique();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Projets ──────────────────────────────────────────
        // Un client possède plusieurs projets (composition)
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('nom_projet', 200);
            $table->timestamps();
            $table->index('client_id');
        });

        // ── Documentations ───────────────────────────────────
        // Un projet contient plusieurs documentations (composition)
        Schema::create('documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->string('titre', 200);
            $table->longText('contenu');
            $table->string('file_path', 500)->nullable();
            $table->timestamps();
            $table->index('projet_id');
        });

        // ── FAQs ─────────────────────────────────────────────
        // Une documentation possède plusieurs FAQ (composition)
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documentation_id')->constrained('documentations')->cascadeOnDelete();
            $table->text('question');
            $table->text('reponse');
            $table->timestamps();
            $table->index('documentation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('documentations');
        Schema::dropIfExists('projets');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('admins');
    }
};
