<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saint_editor_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('role')->default('editor')->index();
            $table->timestamps();
        });

        DB::table('saint_editor_permissions')->insert([
            'email' => 'jeremiahdgage@outlook.com',
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('saint_editor_permissions');
    }
};
