<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exchange_rate_settings', function (Blueprint $table) {
            // `frequency_minutes` cannot be honoured without knowing when the
            // pair last ran, and a failed run writes nothing to `exchange_rates`
            // — so the failure has to be recorded here or it stays invisible to
            // the admin (see PRD 8bis).
            $table->timestamp('last_run_at')->nullable()->after('is_active');
            $table->timestamp('last_error_at')->nullable()->after('last_run_at');
            $table->text('last_error')->nullable()->after('last_error_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_rate_settings', function (Blueprint $table) {
            $table->dropColumn(['last_run_at', 'last_error_at', 'last_error']);
        });
    }
};
