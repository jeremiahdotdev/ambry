<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('create extension if not exists pg_trgm');
        DB::statement('create index if not exists saints_canonical_status_index on saints (canonical_status)');
        DB::statement('create index if not exists saints_primary_name_trgm_index on saints using gin (lower(primary_name) gin_trgm_ops)');
        DB::statement('create index if not exists saints_virtues_trgm_index on saints using gin (lower((virtues::text)) gin_trgm_ops)');
        DB::statement('create index if not exists saints_vices_trgm_index on saints using gin (lower((vices::text)) gin_trgm_ops)');
        DB::statement('create index if not exists saint_aliases_alias_trgm_index on saint_aliases using gin (lower(alias) gin_trgm_ops)');
        DB::statement('create index if not exists patronages_name_trgm_index on patronages using gin (lower(name) gin_trgm_ops)');
        DB::statement('create index if not exists patronages_slug_trgm_index on patronages using gin (lower(slug) gin_trgm_ops)');
        DB::statement('create index if not exists patronages_description_trgm_index on patronages using gin (lower(description) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('drop index if exists patronages_description_trgm_index');
        DB::statement('drop index if exists patronages_slug_trgm_index');
        DB::statement('drop index if exists patronages_name_trgm_index');
        DB::statement('drop index if exists saint_aliases_alias_trgm_index');
        DB::statement('drop index if exists saints_vices_trgm_index');
        DB::statement('drop index if exists saints_virtues_trgm_index');
        DB::statement('drop index if exists saints_primary_name_trgm_index');
        DB::statement('drop index if exists saints_canonical_status_index');
    }
};
