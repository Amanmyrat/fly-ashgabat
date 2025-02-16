<?php

use App\Enum\BookingStatus;
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
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');
            $table->string('booking_reference');
            $table->string('supplier_reference')->nullable();
            $table->json('origin');
            $table->json('destination');
            $table->json('outward');
            $table->json('return')->nullable();
            $table->json('price');
            $table->json('features');
            $table->string('payment_type');
            $table->string('status')->default(BookingStatus::PENDING->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_bookings');
    }
};
