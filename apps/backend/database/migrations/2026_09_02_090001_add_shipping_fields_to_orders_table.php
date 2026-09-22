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
        Schema::table('orders', function (Blueprint $table) {
            // Frozen at checkout, in base_currency — same freezing rule as
            // exchange_rate_applied/payment_amount. Null means "a coordinar":
            // the store had no rate for the destination and the amount is
            // settled outside the system rather than blocking checkout.
            $table->decimal('shipping_amount', 18, 6)->nullable()->after('fulfillment_method_id');

            // Captured when the order is marked shipped (PRD section 6):
            // free-form courier name, an optional tracking/guide number the
            // customer may or may not have gotten, and a free note.
            $table->string('courier')->nullable()->after('shipping_amount');
            $table->string('tracking_code')->nullable()->after('courier');
            $table->text('shipping_note')->nullable()->after('tracking_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_amount', 'courier', 'tracking_code', 'shipping_note']);
        });
    }
};
