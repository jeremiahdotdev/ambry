<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->string('life_dates')->nullable()->after('death_year');
            $table->string('birth_year_qualifier')->nullable()->after('birth_year');
            $table->string('death_year_qualifier')->nullable()->after('death_year');
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            $table->dropColumn([
                'life_dates',
                'birth_year_qualifier',
                'death_year_qualifier',
            ]);
        });
    }
};
