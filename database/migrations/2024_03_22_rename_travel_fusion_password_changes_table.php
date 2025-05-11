<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_fusion_password_changes', function (Blueprint $table) {
            $table->string('login_id')->nullable()->after('password');
        });

        Schema::rename('travel_fusion_password_changes', 'travel_fusion_passwords');
    }

    public function down(): void
    {
        Schema::rename('travel_fusion_passwords', 'travel_fusion_password_changes');
        
        Schema::table('travel_fusion_password_changes', function (Blueprint $table) {
            $table->dropColumn('login_id');
        });
    }
}; 