<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE hotels MODIFY COLUMN address_en VARCHAR(1000) NULL');
        DB::statement('ALTER TABLE hotels MODIFY COLUMN address_ru VARCHAR(1000) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE hotels MODIFY COLUMN address_en VARCHAR(500) NULL');
        DB::statement('ALTER TABLE hotels MODIFY COLUMN address_ru VARCHAR(500) NULL');
    }
};
