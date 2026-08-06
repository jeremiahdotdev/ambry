<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('api_minute_window_started_at')->nullable()->after('remember_token');
            $table->unsignedSmallInteger('api_minute_request_count')->default(0)->after('api_minute_window_started_at');
            $table->timestamp('api_day_window_started_at')->nullable()->after('api_minute_request_count');
            $table->unsignedInteger('api_day_request_count')->default(0)->after('api_day_window_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'api_minute_window_started_at',
                'api_minute_request_count',
                'api_day_window_started_at',
                'api_day_request_count',
            ]);
        });
    }
};
