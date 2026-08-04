<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saints')
            ->where('slug', 'like', 'pope-%')
            ->update([
                'canonical_status' => 'pope',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
