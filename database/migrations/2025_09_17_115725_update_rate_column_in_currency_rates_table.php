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
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->decimal('rate', 30, 15)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->decimal('rate', 10, 6)->change();
        });
    }
};
