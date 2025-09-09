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
        Schema::table('contact_details', function (Blueprint $table) {
            // Make fields nullable that are not required by XMLAgency
            $table->enum('gender', ['male', 'female'])->nullable()->change();
            $table->string('firstname', 100)->nullable()->change();
            $table->string('lastname', 100)->nullable()->change();
            $table->json('address')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_details', function (Blueprint $table) {
            // Revert fields back to required
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
            $table->string('firstname', 100)->nullable(false)->change();
            $table->string('lastname', 100)->nullable(false)->change();
            $table->json('address')->nullable(false)->change();
        });
    }
};
