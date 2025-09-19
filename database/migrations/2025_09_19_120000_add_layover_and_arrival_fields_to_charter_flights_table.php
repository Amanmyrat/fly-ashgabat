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
            // Add layover time fields (hours and minutes)
            $table->integer('layover_hours')->default(0)->after('departure_time');
            $table->integer('layover_minutes')->default(0)->after('layover_hours');
            
            // Add arrival weekday and time fields
            $table->string('arrival_weekday')->after('layover_minutes'); // Monday, Tuesday, etc.
            $table->time('arrival_time')->after('arrival_weekday'); // HH:MM:SS format
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charter_flights', function (Blueprint $table) {
            // Drop the new columns
            $table->dropColumn(['layover_hours', 'layover_minutes', 'arrival_weekday', 'arrival_time']);
        });
    }
};
