<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── HOTELS ─────────────────────────────────────────────────────────────
        //
        // Current state:
        //   name        JSON {"en":"...", "ru":"..."}
        //   name_en     STORED generated from name->>'$.en'  (with index)
        //   name_ru     STORED generated from name->>'$.ru'  (with index)
        //   address     VARCHAR (EN only, with index)
        //
        // Target state:
        //   name_en     VARCHAR  real column  (with index)
        //   name_ru     VARCHAR  real column  (with index)
        //   address_en  VARCHAR  real column  (with index)
        //   address_ru  VARCHAR  real column  (nullable, filled by RU import)

        // 1. Add temporary real columns to hold the data while we restructure.
        DB::statement("ALTER TABLE hotels ADD COLUMN _name_en   VARCHAR(500) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE hotels ADD COLUMN _name_ru   VARCHAR(500) NULL");
        DB::statement("ALTER TABLE hotels ADD COLUMN address_en VARCHAR(500) NULL");
        DB::statement("ALTER TABLE hotels ADD COLUMN address_ru VARCHAR(500) NULL");

        // 2. Copy from existing generated stored columns (fast — no JSON parse needed).
        DB::statement("UPDATE hotels SET _name_en = COALESCE(name_en, ''), _name_ru = name_ru, address_en = address");

        // 3. Drop indexes that reference the generated columns or the old address.
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_name_en");
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_name_ru");
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_address");

        // 4. Drop the generated columns (they depend on `name`).
        DB::statement("ALTER TABLE hotels DROP COLUMN name_en, DROP COLUMN name_ru");

        // 5. Drop the source JSON column and the old single-language address.
        DB::statement("ALTER TABLE hotels DROP COLUMN name, DROP COLUMN address");

        // 6. Rename temp columns to their final names.
        DB::statement("ALTER TABLE hotels RENAME COLUMN _name_en TO name_en");
        DB::statement("ALTER TABLE hotels RENAME COLUMN _name_ru TO name_ru");

        // 7. Re-add indexes.
        DB::statement("CREATE INDEX idx_hotels_name_en    ON hotels (name_en(191))");
        DB::statement("CREATE INDEX idx_hotels_name_ru    ON hotels (name_ru(191))");
        DB::statement("CREATE INDEX idx_hotels_address_en ON hotels (address_en(191))");
        DB::statement("CREATE INDEX idx_hotels_address_ru ON hotels (address_ru(191))");

        // ── REGIONS ────────────────────────────────────────────────────────────
        //
        // Current state:
        //   name     JSON {"en":"...", "ru":"..."}
        //   name_en  STORED generated  (with index)
        //   name_ru  STORED generated  (with index)
        //
        // Target state:
        //   name_en  VARCHAR  real column  (with index)
        //   name_ru  VARCHAR  real column  (with index)

        DB::statement("ALTER TABLE regions ADD COLUMN _name_en VARCHAR(500) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE regions ADD COLUMN _name_ru VARCHAR(500) NULL");

        DB::statement("UPDATE regions SET _name_en = COALESCE(name_en, ''), _name_ru = name_ru");

        DB::statement("ALTER TABLE regions DROP INDEX idx_regions_name_en");
        DB::statement("ALTER TABLE regions DROP INDEX idx_regions_name_ru");

        DB::statement("ALTER TABLE regions DROP COLUMN name_en, DROP COLUMN name_ru");
        DB::statement("ALTER TABLE regions DROP COLUMN name");

        DB::statement("ALTER TABLE regions RENAME COLUMN _name_en TO name_en");
        DB::statement("ALTER TABLE regions RENAME COLUMN _name_ru TO name_ru");

        DB::statement("CREATE INDEX idx_regions_name_en ON regions (name_en(191))");
        DB::statement("CREATE INDEX idx_regions_name_ru ON regions (name_ru(191))");
    }

    public function down(): void
    {
        // Rebuild the JSON columns from the flat columns.
        DB::statement("ALTER TABLE hotels ADD COLUMN name JSON NULL");
        DB::statement("UPDATE hotels SET name = JSON_OBJECT('en', name_en, 'ru', COALESCE(name_ru, ''))");

        DB::statement("ALTER TABLE hotels ADD COLUMN address VARCHAR(500) NULL");
        DB::statement("UPDATE hotels SET address = address_en");

        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_name_en");
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_name_ru");
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_address_en");
        DB::statement("ALTER TABLE hotels DROP INDEX idx_hotels_address_ru");
        DB::statement("ALTER TABLE hotels DROP COLUMN name_en, DROP COLUMN name_ru, DROP COLUMN address_en, DROP COLUMN address_ru");

        DB::statement("ALTER TABLE regions ADD COLUMN name JSON NULL");
        DB::statement("UPDATE regions SET name = JSON_OBJECT('en', name_en, 'ru', COALESCE(name_ru, ''))");

        DB::statement("ALTER TABLE regions DROP INDEX idx_regions_name_en");
        DB::statement("ALTER TABLE regions DROP INDEX idx_regions_name_ru");
        DB::statement("ALTER TABLE regions DROP COLUMN name_en, DROP COLUMN name_ru");
    }
};
