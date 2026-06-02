<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->tinyInteger('star_rating')->nullable()->after('longitude');
            $table->string('kind', 50)->nullable()->after('star_rating');
            $table->string('address')->nullable()->after('kind');
            $table->string('check_in_time', 8)->nullable()->after('address');
            $table->string('check_out_time', 8)->nullable()->after('check_in_time');
            $table->json('images')->nullable()->after('check_out_time');
            $table->json('amenity_groups')->nullable()->after('images');
            $table->json('description_struct')->nullable()->after('amenity_groups');
            $table->json('serp_filters')->nullable()->after('description_struct');
            $table->json('facts')->nullable()->after('serp_filters');
            $table->boolean('is_closed')->default(false)->after('facts');
            $table->boolean('deleted')->default(false)->after('is_closed');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'star_rating',
                'kind',
                'address',
                'check_in_time',
                'check_out_time',
                'images',
                'amenity_groups',
                'description_struct',
                'serp_filters',
                'facts',
                'is_closed',
                'deleted',
            ]);
        });
    }
};
