<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            // Drop old columns if they exist
            $columns = Schema::getColumns('hotel_bookings');
            $columnNames = array_column($columns, 'name');

            if (in_array('hotel_name', $columnNames)) {
                $table->dropColumn('hotel_name');
            }
            if (in_array('check_in_date', $columnNames)) {
                $table->dropColumn('check_in_date');
            }
            if (in_array('check_out_date', $columnNames)) {
                $table->dropColumn('check_out_date');
            }
            if (in_array('nights', $columnNames)) {
                $table->dropColumn('nights');
            }
            if (in_array('city', $columnNames)) {
                $table->dropColumn('city');
            }
            if (in_array('hotel_description', $columnNames)) {
                $table->dropColumn('hotel_description');
            }
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            // Add new columns
            if (!Schema::hasColumn('hotel_bookings', 'hotel_id')) {
                $table->unsignedBigInteger('hotel_id')->nullable()->after('currency')->comment('Link to hotels table (hid)');
                $table->foreign('hotel_id')->references('hid')->on('hotels')->nullOnDelete();
            }
            if (!Schema::hasColumn('hotel_bookings', 'room_type')) {
                $table->string('room_type')->nullable()->after('hotel_id')->comment('Room type from ETG (e.g., Bed in Dorm)');
            }
            if (!Schema::hasColumn('hotel_bookings', 'rooms_count')) {
                $table->integer('rooms_count')->nullable()->after('room_type');
            }
            if (!Schema::hasColumn('hotel_bookings', 'adults_count')) {
                $table->integer('adults_count')->nullable()->after('rooms_count');
            }
            if (!Schema::hasColumn('hotel_bookings', 'children_count')) {
                $table->integer('children_count')->nullable()->after('adults_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('hotel_bookings', 'hotel_id')) {
                $table->dropForeign(['hotel_id']);
                $table->dropColumn('hotel_id');
            }
            if (Schema::hasColumn('hotel_bookings', 'room_type')) {
                $table->dropColumn('room_type');
            }
            if (Schema::hasColumn('hotel_bookings', 'rooms_count')) {
                $table->dropColumn('rooms_count');
            }
            if (Schema::hasColumn('hotel_bookings', 'adults_count')) {
                $table->dropColumn('adults_count');
            }
            if (Schema::hasColumn('hotel_bookings', 'children_count')) {
                $table->dropColumn('children_count');
            }
        });
    }
};
