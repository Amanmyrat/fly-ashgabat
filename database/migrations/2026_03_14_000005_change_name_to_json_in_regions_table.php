<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE `regions` SET `name` = JSON_OBJECT(\'en\', `name`)');

        Schema::table('regions', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE `regions` SET `name` = JSON_UNQUOTE(JSON_EXTRACT(`name`, '$.en'))");

        Schema::table('regions', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
