<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hotels: one FULLTEXT index per language pair (name + address searched together).
        // MATCH() columns must exactly correspond to the index columns.
        DB::statement('ALTER TABLE hotels ADD FULLTEXT INDEX ft_hotels_en (name_en, address_en)');
        DB::statement('ALTER TABLE hotels ADD FULLTEXT INDEX ft_hotels_ru (name_ru, address_ru)');

        // Regions: name-only, single FULLTEXT per language.
        DB::statement('ALTER TABLE regions ADD FULLTEXT INDEX ft_regions_en (name_en)');
        DB::statement('ALTER TABLE regions ADD FULLTEXT INDEX ft_regions_ru (name_ru)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE hotels DROP INDEX ft_hotels_en');
        DB::statement('ALTER TABLE hotels DROP INDEX ft_hotels_ru');
        DB::statement('ALTER TABLE regions DROP INDEX ft_regions_en');
        DB::statement('ALTER TABLE regions DROP INDEX ft_regions_ru');
    }
};
