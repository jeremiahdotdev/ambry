<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saints')
            ->where('canonical_status', 'church_father')
            ->update(['canonical_status' => 'saint']);
    }

    public function down(): void
    {
        //
    }
};
