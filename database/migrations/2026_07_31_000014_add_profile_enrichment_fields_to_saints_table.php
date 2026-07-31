<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'profile_enrichment')) {
                $table->json('profile_enrichment')->nullable()->after('biography_format_error');
            }

            if (! Schema::hasColumn('saints', 'profile_summary')) {
                $table->text('profile_summary')->nullable()->after('profile_enrichment');
            }

            if (! Schema::hasColumn('saints', 'profile_life_span')) {
                $table->json('profile_life_span')->nullable()->after('profile_summary');
            }

            if (! Schema::hasColumn('saints', 'profile_patronages')) {
                $table->json('profile_patronages')->nullable()->after('profile_life_span');
            }

            if (! Schema::hasColumn('saints', 'profile_temperaments')) {
                $table->json('profile_temperaments')->nullable()->after('profile_patronages');
            }

            if (! Schema::hasColumn('saints', 'profile_key_struggles')) {
                $table->json('profile_key_struggles')->nullable()->after('profile_temperaments');
            }

            if (! Schema::hasColumn('saints', 'profile_key_virtues')) {
                $table->json('profile_key_virtues')->nullable()->after('profile_key_struggles');
            }

            if (! Schema::hasColumn('saints', 'profile_church_roles')) {
                $table->json('profile_church_roles')->nullable()->after('profile_key_virtues');
            }

            if (! Schema::hasColumn('saints', 'profile_feast_days')) {
                $table->json('profile_feast_days')->nullable()->after('profile_church_roles');
            }

            if (! Schema::hasColumn('saints', 'profile_related_saints')) {
                $table->json('profile_related_saints')->nullable()->after('profile_feast_days');
            }

            if (! Schema::hasColumn('saints', 'profile_works')) {
                $table->json('profile_works')->nullable()->after('profile_related_saints');
            }

            if (! Schema::hasColumn('saints', 'profile_sources')) {
                $table->json('profile_sources')->nullable()->after('profile_works');
            }

            if (! Schema::hasColumn('saints', 'profile_source_block')) {
                $table->json('profile_source_block')->nullable()->after('profile_sources');
            }

            if (! Schema::hasColumn('saints', 'profile_research_notes')) {
                $table->json('profile_research_notes')->nullable()->after('profile_source_block');
            }

            if (! Schema::hasColumn('saints', 'profile_enrichment_model')) {
                $table->string('profile_enrichment_model')->nullable()->after('profile_research_notes');
            }

            if (! Schema::hasColumn('saints', 'profile_enriched_at')) {
                $table->timestamp('profile_enriched_at')->nullable()->after('profile_enrichment_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'profile_enrichment',
                'profile_summary',
                'profile_life_span',
                'profile_patronages',
                'profile_temperaments',
                'profile_key_struggles',
                'profile_key_virtues',
                'profile_church_roles',
                'profile_feast_days',
                'profile_related_saints',
                'profile_works',
                'profile_sources',
                'profile_source_block',
                'profile_research_notes',
                'profile_enrichment_model',
                'profile_enriched_at',
            ]);
        });
    }
};
