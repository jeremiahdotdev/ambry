<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'image_cutout_url')) {
                $table->string('image_cutout_url')->nullable()->after('image_prompt');
            }

            if (! Schema::hasColumn('saints', 'image_portrait_url')) {
                $table->string('image_portrait_url')->nullable()->after('image_cutout_url');
            }

            if (! Schema::hasColumn('saints', 'image_thumb_url')) {
                $table->string('image_thumb_url')->nullable()->after('image_portrait_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'image_cutout_url',
                'image_portrait_url',
                'image_thumb_url',
            ]);
        });
    }
};
