<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin123!'),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('admins')->where('email', 'admin@test.com')->delete();
    }
};
