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
        Schema::create('charter_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_from_id')->constrained('cities')->onDelete('cascade');
            $table->foreignId('city_to_id')->constrained('cities')->onDelete('cascade');
            $table->dateTime('departure_datetime');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charter_flights');
    }
}; 