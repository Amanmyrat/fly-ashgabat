<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop heavy JSON columns not needed for search or listing.
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['amenity_groups', 'description_struct', 'facts', 'is_closed', 'deleted']);
        });

        // Add STORED generated columns so we can put a plain index on them
        // and run fast prefix-based LIKE queries without full table scans.
        DB::statement("
            ALTER TABLE hotels
            ADD COLUMN name_en VARCHAR(500) GENERATED ALWAYS AS (name->>'$.en') STORED,
            ADD COLUMN name_ru VARCHAR(500) GENERATED ALWAYS AS (name->>'$.ru') STORED
        ");

        DB::statement('CREATE INDEX idx_hotels_name_en ON hotels (name_en(191))');
        DB::statement('CREATE INDEX idx_hotels_name_ru ON hotels (name_ru(191))');
        DB::statement('CREATE INDEX idx_hotels_address  ON hotels (address(191))');

        // Same for regions.
        DB::statement("
            ALTER TABLE regions
            ADD COLUMN name_en VARCHAR(500) GENERATED ALWAYS AS (name->>'$.en') STORED,
            ADD COLUMN name_ru VARCHAR(500) GENERATED ALWAYS AS (name->>'$.ru') STORED
        ");

        DB::statement('CREATE INDEX idx_regions_name_en ON regions (name_en(191))');
        DB::statement('CREATE INDEX idx_regions_name_ru ON regions (name_ru(191))');
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropIndex('idx_hotels_name_en');
            $table->dropIndex('idx_hotels_name_ru');
            $table->dropIndex('idx_hotels_address');
            $table->dropColumn(['name_en', 'name_ru']);
            $table->json('amenity_groups')->nullable();
            $table->json('description_struct')->nullable();
            $table->json('facts')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('deleted')->default(false);
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropIndex('idx_regions_name_en');
            $table->dropIndex('idx_regions_name_ru');
            $table->dropColumn(['name_en', 'name_ru']);
        });
    }
};
