<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fulfillment_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            // Null means the rate applies to the whole state; a specific
            // municipality row overrides it for that municipality only.
            $table->foreignId('municipality_id')->nullable()->constrained()->cascadeOnDelete();
            // Null means "a coordinar" for this exact zone, distinct from no
            // row existing at all (which falls back to the method's base_cost).
            $table->decimal('cost', 18, 6)->nullable();
            $table->timestamps();

            $table->unique(['fulfillment_method_id', 'state_id', 'municipality_id']);
        });

        // Postgres treats NULLs as distinct in a unique index, so the
        // constraint above does not stop two state-wide rows (municipality_id
        // null) for the same method/state. This partial index closes that gap
        // without touching the municipality-specific rows it left alone.
        DB::statement(
            'CREATE UNIQUE INDEX fulfillment_zone_rates_state_wide_unique '.
            'ON fulfillment_zone_rates (fulfillment_method_id, state_id) '.
            'WHERE municipality_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_zone_rates');
    }
};
