<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data will be repopulated on the next reimport.
        DB::statement('TRUNCATE TABLE hotel_reviews');
        DB::statement('TRUNCATE TABLE hotel_review_stats');

        Schema::table('hotel_reviews', function (Blueprint $table) {
            $table->string('room_name', 500)->nullable()->after('comment');
            $table->tinyInteger('adults')->unsigned()->nullable()->after('room_name');
            $table->tinyInteger('children')->unsigned()->nullable()->after('adults');
            $table->string('traveller_type', 50)->nullable()->after('children');
            $table->string('trip_type', 50)->nullable()->after('traveller_type');
            $table->float('score_cleanness')->nullable()->after('trip_type');
            $table->float('score_location')->nullable()->after('score_cleanness');
            $table->float('score_price')->nullable()->after('score_location');
            $table->float('score_services')->nullable()->after('score_price');
            $table->float('score_room')->nullable()->after('score_services');
            $table->float('score_meal')->nullable()->after('score_room');
        });

        Schema::table('hotel_review_stats', function (Blueprint $table) {
            $table->float('score_cleanness')->nullable()->after('avg_rating');
            $table->float('score_location')->nullable()->after('score_cleanness');
            $table->float('score_price')->nullable()->after('score_location');
            $table->float('score_services')->nullable()->after('score_price');
            $table->float('score_room')->nullable()->after('score_services');
            $table->float('score_meal')->nullable()->after('score_room');
            $table->float('score_wifi')->nullable()->after('score_meal');
            $table->float('score_hygiene')->nullable()->after('score_wifi');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'room_name', 'adults', 'children', 'traveller_type', 'trip_type',
                'score_cleanness', 'score_location', 'score_price',
                'score_services', 'score_room', 'score_meal',
            ]);
        });

        Schema::table('hotel_review_stats', function (Blueprint $table) {
            $table->dropColumn([
                'score_cleanness', 'score_location', 'score_price',
                'score_services', 'score_room', 'score_meal',
                'score_wifi', 'score_hygiene',
            ]);
        });
    }
};
