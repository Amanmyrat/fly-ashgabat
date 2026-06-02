<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etg_dump_status', function (Blueprint $table) {
            $table->id();

            // 'hotel' or 'region' — one row per dump type
            $table->string('type')->unique();

            // idle | downloading | decompressing | importing | finished | failed
            $table->string('status')->default('idle');

            // Current phase progress 0–100
            $table->unsignedTinyInteger('progress')->default(0);

            // Download tracking
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->nullable();

            // Import tracking
            $table->unsignedBigInteger('records_processed')->nullable();
            $table->unsignedBigInteger('total_records')->nullable();

            // Timestamps
            $table->string('last_update')->nullable();   // ISO-8601 from ETG API
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etg_dump_status');
    }
};
