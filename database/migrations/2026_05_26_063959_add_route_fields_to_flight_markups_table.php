<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_markups', function (Blueprint $table) {
            $table->string('departure_code', 3)->nullable()->after('airline_code');
            $table->string('arrival_code', 3)->nullable()->after('departure_code');

            $table->index(['supplier', 'departure_code', 'arrival_code']);
            $table->index(['supplier', 'airline_code', 'departure_code', 'arrival_code'], 'flight_markups_supplier_airline_route_idx');
        });
    }

    public function down(): void
    {
        Schema::table('flight_markups', function (Blueprint $table) {
            $table->dropIndex(['supplier', 'departure_code', 'arrival_code']);
            $table->dropIndex('flight_markups_supplier_airline_route_idx');

            $table->dropColumn([
                'departure_code',
                'arrival_code',
            ]);
        });
    }
};
