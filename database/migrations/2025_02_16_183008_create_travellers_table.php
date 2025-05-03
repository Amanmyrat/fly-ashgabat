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
        Schema::create('travellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_booking_id')
                ->constrained()
                ->onDelete('cascade');
            $table->date('birthdate');
            $table->string('passport_number', 50);
            $table->date('passport_expiry_date');
            $table->string('passport_country', 2);
            $table->string('nationality', 2);
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->enum('gender', ['male', 'female']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travellers');
    }
};
