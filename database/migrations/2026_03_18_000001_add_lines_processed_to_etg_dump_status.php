<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etg_dump_status', function (Blueprint $table) {
            // Lines read from file (for progress). total_records = total lines; records_processed = inserted.
            $table->unsignedBigInteger('lines_processed')->nullable()->after('records_processed');
        });
    }

    public function down(): void
    {
        Schema::table('etg_dump_status', function (Blueprint $table) {
            $table->dropColumn('lines_processed');
        });
    }
};
