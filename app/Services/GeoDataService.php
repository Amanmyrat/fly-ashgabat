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
     * @param string $code Code.
     * @return array An array of information.
     */
    public function getAirportInfo(string $code): array
    {
        $airports = $this->airportDataRepository->getAllAirports();

        return [
            'cityName' =>  $airports[$code]['cityName']['en'],
            'airportName' => $airports[$code]['airportName']['en'] ?? '',
        ];
    }
}
