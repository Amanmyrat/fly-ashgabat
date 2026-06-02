<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->string('hotel_name')->nullable()->after('currency');
            $table->dateTime('check_in_date')->nullable()->after('hotel_name');
            $table->dateTime('check_out_date')->nullable()->after('check_in_date');
            $table->integer('nights')->nullable()->after('check_out_date');
            $table->integer('rooms_count')->nullable()->after('nights');
            $table->integer('adults_count')->nullable()->after('rooms_count');
            $table->integer('children_count')->nullable()->after('adults_count');
            $table->string('city')->nullable()->after('children_count');
            $table->text('hotel_description')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'hotel_name',
                'check_in_date',
                'check_out_date',
                'nights',
                'rooms_count',
                'adults_count',
                'children_count',
                'city',
                'hotel_description',
            ]);
        });
    }
};
