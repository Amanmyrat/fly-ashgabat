<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert every existing plain string value into a JSON object {"en": "<value>"}
        // so no data is lost during the column type change.
        DB::statement('UPDATE `hotels` SET `name` = JSON_OBJECT(\'en\', `name`)');

        Schema::table('hotels', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        // Restore the English translation back to a plain string.
        DB::statement("UPDATE `hotels` SET `name` = JSON_UNQUOTE(JSON_EXTRACT(`name`, '$.en'))");

        Schema::table('hotels', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
