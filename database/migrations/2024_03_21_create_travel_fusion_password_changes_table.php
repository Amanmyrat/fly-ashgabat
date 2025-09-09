<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_fusion_passwords', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('password');
            $table->string('login_id')->nullable();
            $table->timestamp('changed_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_fusion_passwords');
    }
}; 