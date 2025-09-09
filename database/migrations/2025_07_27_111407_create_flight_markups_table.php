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
        Schema::create('flight_markups', function (Blueprint $table) {
            $table->id();
            $table->string('supplier');
            $table->string('airline_code', 2)->nullable();
            $table->decimal('markup_percentage', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['supplier', 'airline_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_markups');
    }
};
