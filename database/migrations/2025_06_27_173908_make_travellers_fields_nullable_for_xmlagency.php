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
        Schema::table('travellers', function (Blueprint $table) {
            // Make fields nullable that are not required by XMLAgency
            $table->date('passport_expiry_date')->nullable()->change();
            $table->string('passport_country', 2)->nullable()->change();
            
            // Also need to adjust nationality field length for XMLAgency (3-letter codes)
            $table->string('nationality', 3)->change();
            
            // Add middlename field for XMLAgency support (optional)
            $table->string('middlename', 100)->nullable()->after('lastname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travellers', function (Blueprint $table) {
            // Revert fields back to required
            $table->date('passport_expiry_date')->nullable(false)->change();
            $table->string('passport_country', 2)->nullable(false)->change();
            
            // Revert nationality back to 2-letter codes
            $table->string('nationality', 2)->change();
            
            // Remove middlename field
            $table->dropColumn('middlename');
        });
    }
};
