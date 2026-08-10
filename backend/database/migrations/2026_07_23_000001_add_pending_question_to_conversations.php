<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Question exacte qui a déclenché l'escalade — utilisée pour
            // savoir quelle question apprendre en FAQ quand le support
            // répond, indépendamment de messages ultérieurs.
            $table->text('pending_question')->nullable()->after('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('pending_question');
        });
    }
};
