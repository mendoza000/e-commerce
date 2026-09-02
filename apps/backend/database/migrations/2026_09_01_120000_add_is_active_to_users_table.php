<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin accounts are deactivated, never deleted: an operator who processed
     * orders has to stay resolvable from `order_status_history.changed_by`
     * forever. `is_active` (rather than a `deactivated_at` timestamp) keeps the
     * column consistent with every other toggle in the schema — products,
     * variants, payment methods, fulfillment methods, exchange rate settings.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
