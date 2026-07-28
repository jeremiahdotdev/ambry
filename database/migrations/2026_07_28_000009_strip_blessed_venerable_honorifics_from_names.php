<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::table('saints')
                ->whereIn('canonical_status', ['blessed', 'venerable'])
                ->update([
                    'primary_name' => DB::raw("trim(regexp_replace(primary_name, '^(Bl\\.?|Blessed|Ven\\.?|Venerable)\\s+', '', 'i'))"),
                ]);

            return;
        }

        DB::table('saints')
            ->whereIn('canonical_status', ['blessed', 'venerable'])
            ->orderBy('id')
            ->get(['id', 'primary_name'])
            ->each(function (object $saint): void {
                $name = trim(preg_replace('/^(?:Bl\.?|Blessed|Ven\.?|Venerable)\s+/iu', '', $saint->primary_name) ?? $saint->primary_name);

                if ($name !== $saint->primary_name) {
                    DB::table('saints')
                        ->where('id', $saint->id)
                        ->update(['primary_name' => $name]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
