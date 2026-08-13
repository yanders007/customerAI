<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_configs', function (Blueprint $table) {
            $table->string('label', 120)->nullable()->after('model');
            $table->timestamp('last_used_at')->nullable()->after('is_active');
            $table->index(['is_active', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_configs', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'updated_at']);
            $table->dropColumn(['label', 'last_used_at']);
        });
    }
};
