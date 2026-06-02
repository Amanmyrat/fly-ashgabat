<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->float('rating');
            $table->text('title')->nullable();
            $table->text('comment')->nullable();
            $table->string('author_name', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('hotel_review_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('hotel_id')->primary();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->float('avg_rating')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_review_stats');
        Schema::dropIfExists('hotel_reviews');
    }
};
