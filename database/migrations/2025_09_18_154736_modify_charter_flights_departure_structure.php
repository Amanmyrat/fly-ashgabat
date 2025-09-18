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
        Schema::table('charter_flights', function (Blueprint $table) {
            // Drop the existing departure_datetime column
            $table->dropColumn('departure_datetime');
            
            // Add new columns for weekday and time
            $table->string('departure_weekday')->after('city_to_id'); // Monday, Tuesday, etc.
            $table->time('departure_time')->after('departure_weekday'); // HH:MM:SS format
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charter_flights', function (Blueprint $table) {
            // Drop the new columns
            $table->dropColumn(['departure_weekday', 'departure_time']);
            
            // Restore the original departure_datetime column
            $table->dateTime('departure_datetime')->after('city_to_id');
        });
    }
};
