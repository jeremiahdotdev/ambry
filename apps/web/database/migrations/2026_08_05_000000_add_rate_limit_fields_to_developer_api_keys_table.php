<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->timestamp('request_window_started_at')->nullable()->after('last_used_at');
            $table->unsignedSmallInteger('request_window_count')->default(0)->after('request_window_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->dropColumn([
                'request_window_started_at',
                'request_window_count',
            ]);
        });
    }
};
