<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'image_page_variant')) {
                $table->string('image_page_variant')->nullable()->after('image_thumb_url');
            }

            if (! Schema::hasColumn('saints', 'image_key_colors')) {
                $table->json('image_key_colors')->nullable()->after('image_page_variant');
            }

            if (! Schema::hasColumn('saints', 'image_variant_reason')) {
                $table->text('image_variant_reason')->nullable()->after('image_key_colors');
            }

            if (! Schema::hasColumn('saints', 'image_variant_confidence')) {
                $table->decimal('image_variant_confidence', 4, 3)->nullable()->after('image_variant_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'image_page_variant',
                'image_key_colors',
                'image_variant_reason',
                'image_variant_confidence',
            ]);
        });
    }
};
