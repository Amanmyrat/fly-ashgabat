<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('country_name_en', 500)->nullable()->after('name_ru');
            $table->string('country_name_ru', 500)->nullable()->after('country_name_en');
            $table->decimal('latitude', 10, 7)->nullable()->after('country_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('iata', 10)->nullable()->after('longitude');

            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['country_name_en', 'country_name_ru', 'latitude', 'longitude', 'iata']);
            $table->unsignedBigInteger('parent_id')->nullable()->index();
        });
    }
};
