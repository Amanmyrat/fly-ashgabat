<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('hotels', 'etg_id')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->string('etg_id', 50)->nullable()->after('hid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['etg_id']);
            $table->dropColumn('etg_id');
        });
    }
};
