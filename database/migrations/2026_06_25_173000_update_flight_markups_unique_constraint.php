<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_markups', function (Blueprint $table) {
            $table->dropUnique(['supplier', 'airline_code']);
            $table->dropIndex('flight_markups_supplier_airline_route_idx');

            $table->unique(
                ['supplier', 'airline_code', 'departure_code', 'arrival_code'],
                'flight_markups_supplier_airline_route_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('flight_markups', function (Blueprint $table) {
            $table->dropUnique('flight_markups_supplier_airline_route_unique');

            $table->unique(['supplier', 'airline_code']);
            $table->index(
                ['supplier', 'airline_code', 'departure_code', 'arrival_code'],
                'flight_markups_supplier_airline_route_idx'
            );
        });
    }
};
