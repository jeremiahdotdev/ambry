<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->json('virtues')->nullable()->after('is_doctor');
            $table->json('vices')->nullable()->after('virtues');
            $table->json('roles')->nullable()->after('vices');
            $table->text('ai_reason')->nullable()->after('roles');
            $table->decimal('ai_confidence', 4, 3)->nullable()->after('ai_reason');
            $table->text('image_prompt')->nullable()->after('ai_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'virtues',
                'vices',
                'roles',
                'ai_reason',
                'ai_confidence',
                'image_prompt',
            ]);
        });
    }
};
