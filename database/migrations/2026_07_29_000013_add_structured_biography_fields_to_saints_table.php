<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->json('biography_sections')->nullable()->after('biography');
            $table->json('biography_sources')->nullable()->after('biography_sections');
            $table->string('biography_format_model')->nullable()->after('biography_sources');
            $table->timestamp('biography_formatted_at')->nullable()->after('biography_format_model');
            $table->text('biography_format_error')->nullable()->after('biography_formatted_at');
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'biography_sections',
                'biography_sources',
                'biography_format_model',
                'biography_formatted_at',
                'biography_format_error',
            ]);
        });
    }
};
