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
        Schema::create('exchange_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->string('mode');
            $table->string('provider', 50)->nullable();
            $table->unsignedInteger('frequency_minutes')->nullable();
            $table->decimal('reference_amount', 18, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_currency_id', 'to_currency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_settings');
    }
};
