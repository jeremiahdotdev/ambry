<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('saints', 'short_biography') && ! Schema::hasColumn('saints', 'biography')) {
            Schema::table('saints', function (Blueprint $table): void {
                $table->renameColumn('short_biography', 'biography');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('saints', 'biography') && ! Schema::hasColumn('saints', 'short_biography')) {
            Schema::table('saints', function (Blueprint $table): void {
                $table->renameColumn('biography', 'short_biography');
            });
        }
    }
};
