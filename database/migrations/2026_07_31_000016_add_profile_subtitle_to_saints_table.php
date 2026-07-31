<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (! Schema::hasColumn('saints', 'profile_subtitle')) {
                $table->string('profile_subtitle')->nullable()->after('profile_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saints', function (Blueprint $table): void {
            if (Schema::hasColumn('saints', 'profile_subtitle')) {
                $table->dropColumn('profile_subtitle');
            }
        });
    }
};
