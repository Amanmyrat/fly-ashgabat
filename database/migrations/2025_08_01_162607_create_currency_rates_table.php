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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3)->default('RUB'); // Source currency
            $table->string('to_currency', 3)->default('USD'); // Target currency
            $table->decimal('rate', 10, 6); // Conversion rate: 1 FROM_CURRENCY = X TO_CURRENCY (e.g., 83 means 1 USD = 83 RUB)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable(); // Optional notes about the rate
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['from_currency', 'to_currency', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
