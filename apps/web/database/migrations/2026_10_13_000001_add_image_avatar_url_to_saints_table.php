<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->text('image_avatar_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn('image_avatar_url');
        });
    }
};
