<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'profile_landmarks')) {
                $table->json('profile_landmarks')->nullable()->after('profile_works');
            }
        });

        Schema::table('saints', function (Blueprint $table): void {
            $columns = collect([
                'profile_enrichment',
                'profile_enrichment_model',
                'profile_enriched_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('saints', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (Schema::hasColumn('saints', 'profile_landmarks')) {
                $table->dropColumn('profile_landmarks');
            }
        });

        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'profile_enrichment')) {
                $table->json('profile_enrichment')->nullable()->after('biography_format_error');
            }

            if (! Schema::hasColumn('saints', 'profile_enrichment_model')) {
                $table->string('profile_enrichment_model')->nullable()->after('profile_research_notes');
            }

            if (! Schema::hasColumn('saints', 'profile_enriched_at')) {
                $table->timestamp('profile_enriched_at')->nullable()->after('profile_enrichment_model');
            }
        });
    }
};
