<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('messages', function (Blueprint $table) {
            // Tokens consommés par ce message
            $table->unsignedInteger('tokens_input')->default(0)->after('content');
            $table->unsignedInteger('tokens_output')->default(0)->after('tokens_input');
            // Source du contexte envoyé à l'IA (chunks RAG ou doc complète)
            $table->enum('retrieval_source', ['faq', 'chunks', 'fallback', 'smalltalk', 'none'])
                  ->default('none')->after('tokens_output');
            // Nombre de chunks sélectionnés (0 si fallback ou smalltalk)
            $table->unsignedTinyInteger('chunks_used')->default(0)->after('retrieval_source');
        });
    }
    public function down(): void {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['tokens_input','tokens_output','retrieval_source','chunks_used']);
        });
    }
};
