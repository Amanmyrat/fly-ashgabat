<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etg_dump_status', function (Blueprint $table) {
            // Drop the old per-type unique constraint before adding the column.
            $table->dropUnique(['type']);

            // One row per (type, language) pair.
            $table->string('language', 8)->after('type')->default('en');
        });

        // Existing rows (if any) already have language='en' from the column default.
        // Add the composite unique index now that all rows have a language value.
        Schema::table('etg_dump_status', function (Blueprint $table) {
            $table->unique(['type', 'language']);
        });
    }

    public function down(): void
    {
        Schema::table('etg_dump_status', function (Blueprint $table) {
            $table->dropUnique(['type', 'language']);
            $table->dropColumn('language');
            $table->unique('type');
        });
    }
};
