<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_order_id')->unique();
            $table->unsignedBigInteger('etg_order_id')->nullable()->index();
            $table->string('status', 32);
            $table->string('payment_type', 32);
            $table->string('book_hash', 512)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('contact_email');
            $table->string('contact_phone', 64);
            $table->json('guests')->nullable();
            /** Full JSON returned to the client (ETG confirmation snapshot). */
            $table->json('api_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
