<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\CharterFlight;
use Illuminate\Database\Seeder;

class CharterFlightSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create sample cities
        $cities = [
            [
                'name' => ['en' => 'Ashgabat', 'ru' => 'Ашхабад', 'tm' => 'Aşgabat'],
                'code' => 'ASB'
            ],
            [
                'name' => ['en' => 'Istanbul', 'ru' => 'Стамбул', 'tm' => 'Stambul'],
                'code' => 'IST'
            ],
            [
                'name' => ['en' => 'Dubai', 'ru' => 'Дубай', 'tm' => 'Dubaý'],
                'code' => 'DXB'
            ],
            [
                'name' => ['en' => 'Moscow', 'ru' => 'Москва', 'tm' => 'Moskwa'],
                'code' => 'SVO'
            ],
            [
                'name' => ['en' => 'Frankfurt', 'ru' => 'Франкфурт', 'tm' => 'Frankfurt'],
                'code' => 'FRA'
            ],
        ];

        foreach ($cities as $cityData) {
            City::create($cityData);
        }

        // Get created cities
        $ashgabat = City::where('code', 'ASB')->first();
        $istanbul = City::where('code', 'IST')->first();
        $dubai = City::where('code', 'DXB')->first();
        $moscow = City::where('code', 'SVO')->first();
        $frankfurt = City::where('code', 'FRA')->first();

        // Create sample charter flights
        $flights = [
            // From Ashgabat
            [
                'city_from_id' => $ashgabat->id,
                'city_to_id' => $istanbul->id,
                'departure_datetime' => now()->addDays(7)->setTime(10, 30),
                'price' => 450.00
            ],
            [
                'city_from_id' => $ashgabat->id,
                'city_to_id' => $istanbul->id,
                'departure_datetime' => now()->addDays(14)->setTime(15, 45),
                'price' => 480.00
            ],
            [
                'city_from_id' => $ashgabat->id,
                'city_to_id' => $dubai->id,
                'departure_datetime' => now()->addDays(10)->setTime(8, 15),
                'price' => 520.00
            ],
            [
                'city_from_id' => $ashgabat->id,
                'city_to_id' => $moscow->id,
                'departure_datetime' => now()->addDays(12)->setTime(14, 20),
                'price' => 380.00
            ],
            
            // From Istanbul
            [
                'city_from_id' => $istanbul->id,
                'city_to_id' => $ashgabat->id,
                'departure_datetime' => now()->addDays(8)->setTime(16, 30),
                'price' => 460.00
            ],
            [
                'city_from_id' => $istanbul->id,
                'city_to_id' => $frankfurt->id,
                'departure_datetime' => now()->addDays(5)->setTime(11, 45),
                'price' => 320.00
            ],
            
            // From Dubai
            [
                'city_from_id' => $dubai->id,
                'city_to_id' => $ashgabat->id,
                'departure_datetime' => now()->addDays(11)->setTime(9, 30),
                'price' => 530.00
            ],
        ];

        foreach ($flights as $flightData) {
            CharterFlight::create($flightData);
        }
    }
} 