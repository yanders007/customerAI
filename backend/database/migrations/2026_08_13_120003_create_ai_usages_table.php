<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('ai_config_id')->nullable()->constrained('ai_configs')->nullOnDelete();
            $table->string('request_type', 40); // chat, faq, smalltalk, embedding_query, embedding_index, config_test
            $table->string('provider', 50)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->unsignedInteger('embedding_tokens')->default(0);
            $table->boolean('is_estimated')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
            $table->index(['request_type', 'created_at']);
            $table->index(['provider', 'model', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
