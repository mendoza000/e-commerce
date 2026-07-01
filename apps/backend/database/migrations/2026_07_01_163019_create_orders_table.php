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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status');
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('document_type', 4);
            $table->string('document_number', 20);
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->restrictOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained('parishes')->restrictOnDelete();
            $table->text('address_reference')->nullable();
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->decimal('base_amount', 18, 6);
            $table->foreignId('payment_currency_id')->constrained('currencies');
            $table->decimal('exchange_rate_applied', 18, 6);
            $table->decimal('payment_amount', 18, 6);
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('fulfillment_method_id')->nullable()->constrained('fulfillment_methods')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
