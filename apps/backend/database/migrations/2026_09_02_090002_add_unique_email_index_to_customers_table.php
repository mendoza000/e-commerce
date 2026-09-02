<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guest checkout never writes to `customers` — only registration does
     * (Fase 6) — so this table has been unused in practice until now, and
     * adding the index retroactively carries no risk of pre-existing
     * duplicates. Postgres allows any number of NULL emails through a unique
     * index, which is what a customer who never registered still is.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
