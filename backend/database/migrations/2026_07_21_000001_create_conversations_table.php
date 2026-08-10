<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations : une par session de chat
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->string('uuid', 36)->unique(); // identifiant public pour le lien mail
            $table->enum('status', ['open', 'escalated', 'resolved'])->default('open');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
            $table->index('client_id');
            $table->index('projet_id');
        });

        // Messages : chaque échange dans une conversation
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'human_support']);
            $table->longText('content');
            $table->timestamps();
            $table->index('conversation_id');
        });

        // Tokens de reset de mot de passe (admin + client)
        Schema::create('password_reset_tokens_custom', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->enum('type', ['admin', 'client']);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->index(['email', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens_custom');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
