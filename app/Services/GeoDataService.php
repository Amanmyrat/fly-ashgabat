<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;
use Illuminate\Support\Facades\File;

class GeoDataService
{
    public function __construct(protected AirportDataRepositoryInterface $airportDataRepository)
    {
    }

    public function getNationality(): array
    {
        $nationalities = [];

        $filePath = public_path('/geodata/countries.xml');

        if (!File::exists($filePath)) {
            return [];
        }

        $fileContent = File::get($filePath);
        $countries = simplexml_load_string($fileContent);

        foreach ((array)$countries as $item) {
            if (count($item) > 8) {
                foreach ($item as $country) {
                    $co = (array)$country;
                    $nationalities[] = [
                        'key' => $co['alpha2'],
                        'iso' => $co['alpha3'],
                        'country' => $co['name'],
                        'country_translate' => $this->translate($co['name']),
                        'country_en' => $co['english'],
                    ];
                }
            }
        }
        return $nationalities;
    }

    /**
     * Translate a string into a URL-friendly format.
     *
     * @param string $value The value to be translated.
     * @return string The translated string.
     */
    private function translate(string $value): string
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        );

        return strtr($value, $converter);
    }

    /**
     * Get information about airports for booking purposes.
     *
     * @param array $location Location.
     * @return array An array of information.
     */
    public function getAirportInfo(array $location): array
    {
        $airports = $this->airportDataRepository->getAllAirports();
        $cities = $this->airportDataRepository->getAllCities();

        return $location['Type'] === 'airport' ? [
            'cityName' =>  $airports[$location['Code']]['cityName']['en'],
            'airportName' => $airports[$location['Code']]['airportName']['en'] ?? '',
        ] : [
            'cityName' =>  $cities[$location['Code']]['name']['en'],
            'airportName' => '',
        ];
    }


    /**
     * Retrieve detailed information about a specific flight route, including airports, airlines, and aircraft.
     *
     * This method gathers comprehensive data for a given flight route based on the provided airport codes,
     * aircraft type, and airline codes. It compiles information about the departure and arrival airports,
     * operating and marketing airlines, and the aircraft used.
     *
     * @param string $DepAirportCode The IATA code for the departure airport.
     * @param string $ArrAirportCode The IATA code for the arrival airport.
     * @param string $AircraftType The code representing the type of aircraft used for the flight.
     * @param string $OpAirline The IATA or ICAO code of the operating airline.
     * @param string $MarkAirline The IATA or ICAO code of the marketing airline.
     * @return array An associative array containing detailed information about the flight route. The structure includes:
     *               - 'depCode': Array containing 'code' for the departure airport and 'airport' name.
     *               - 'arrCode': Array containing 'code' for the arrival airport and 'airport' name.
     *               - 'opAirline': Array containing 'code' of the operating airline and nested 'airline' info with 'name' and 'logo'.
     *               - 'markAirline': Array containing 'code' of the marketing airline and nested 'airline' info with 'name' and 'logo'.
     *               - 'aircraftType': Array containing 'code' of the aircraft type and 'aircraft' name.
     */
    public function getInfo(string $DepAirportCode, string $ArrAirportCode, string $AircraftType, string $OpAirline, string $MarkAirline): array
    {
        $airports = $this->airportDataRepository->getAllAirports();
        $airCrafts = $this->airportDataRepository->getAllAirCrafts();
        $airlines = $this->airportDataRepository->getAllAirlines();

        return [
            'data' => [
                'depCode' => [
                    'code' => $DepAirportCode,
                    'airport' => $airports[$DepAirportCode]['airportName'] ?? $airports[$DepAirportCode]['cityName']
                ],
                'arrCode' => [
                    'code' => $ArrAirportCode,
                    'airport' => $airports[$ArrAirportCode]['airportName'] ?? $airports[$ArrAirportCode]['cityName']
                ],
                'opAirline' => [
                    'code' => $OpAirline,
                    'airline' => [
                        'name' => isset($airlines[$OpAirline]) ? $airlines[$OpAirline]['name'] : null,
                        'logo' => isset($airlines[$OpAirline]['logo']['file'])
                            ? asset('storage/airline_logos/' . $airlines[$OpAirline]['logo']['file'])
                            : null
                    ]
                ],
                'markAirline' => [
                    'code' => $MarkAirline,
                    'airline' => [
                        'name' => isset($airlines[$MarkAirline]) ? $airlines[$MarkAirline]['name'] : null,
                        'logo' => isset($airlines[$MarkAirline]['logo']['file'])
                            ? asset('storage/airline_logos/' . $airlines[$MarkAirline]['logo']['file'])
                            : null
                    ]
                ],
                'aircraftType' => [
                    'code' => $AircraftType,
                    'aircraft' => $airCrafts[$AircraftType]['name'] ?? null
                ]
            ]
        ];
    }
}
