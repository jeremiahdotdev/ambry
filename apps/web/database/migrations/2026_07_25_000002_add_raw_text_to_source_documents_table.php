<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_documents', function (Blueprint $table): void {
            $table->longText('raw_text')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('source_documents', function (Blueprint $table): void {
            $table->dropColumn('raw_text');
        });
    }
};
