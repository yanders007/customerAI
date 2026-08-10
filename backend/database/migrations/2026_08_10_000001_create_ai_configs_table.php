<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();

            // Provider : openai | gemini | anthropic | mistral | groq |
            //            deepseek | together | openrouter | perplexity | xai | cohere-chat
            $table->string('provider', 50);

            // Modèle sélectionné (ex: gemini-1.5-flash, gpt-4o-mini…)
            $table->string('model', 100);

            // Clé API chiffrée via Laravel encrypt()
            $table->text('api_key_encrypted');

            // Une seule config active à la fois
            $table->boolean('is_active')->default(true);

            // Prompt système personnalisable (null = prompt par défaut)
            $table->text('system_prompt')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_configs');
    }
};
