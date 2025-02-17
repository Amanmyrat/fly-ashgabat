<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;

class AirportLocatorService
{
    public function __construct(protected AirportDataRepositoryInterface $airportDataRepository)
    {
    }

    /**
     * Search for airports based on a query.
     *
     * @param string $query The search query.
     * @return array An array of airport search results.
     */
    public function searchAirports(string $query): array
    {
        $airports = $this->airportDataRepository->getAllAirports();
        $countries = $this->airportDataRepository->getAllCountries();
        $cities = $this->airportDataRepository->getAllCities();

        // Refactored Functions
        $airports = $this->processAirports($airports, $countries, $cities);

        $arr = $this->filterAirportsByQuery($airports, $query);
        return $this->groupAirports($arr);
    }

    /**
     * Process airport data, including country and city information.
     *
     * @param array $airports The array of airport data.
     * @param array $countries The array of country data.
     * @param array $cities The array of city data.
     * @return array Processed airport data.
     */
    private function processAirports(array $airports, array $countries, array $cities): array
    {
        // Process each airport entry
        foreach ($airports as $aKey => $airport) {
            // Update country information if available
            if ($airport['country'] != null && isset($countries[$airport['country']])) {
                $airports[$aKey]['country'] = $countries[$airport['country']]['name'];
            }

            // Set airport code
            $airports[$aKey]['code'] = $aKey;

            // Determine city information
            $airports[$aKey]['city'] = isset($airport['airportName'])
                ? $this->getCityString($airport, $countries)
                : $airport['cityName']['ru'] . ', ' . $countries[$airport['country']]['name']['ru'];

            // Update city information
            if ($airport['area'] != null && isset($cities[$airport['area']])) {
                $airports[$aKey]['area'] = [
                    'name' => $cities[$airport['area']]['name'],
                    'code' => $airports[$aKey]['area']
                ];
            }
        }
        return $airports;
    }

    /**
     * Get the city information string for an airport.
     *
     * @param array $airport The airport data.
     * @param array $countries The array of country data.
     * @return string The city information string.
     */
    private function getCityString(array $airport, array $countries): string
    {
        return isset($airport['airportName'])
            ? $airport['airportName']['ru'] . ', ' . $airport['cityName']['ru'] . ', ' . $countries[$airport['country']]['name']['ru']
            : $airport['cityName']['ru'] . ', ' . $countries[$airport['country']]['name']['ru'];
    }

    /**
     * Filter airports based on the search query.
     *
     * @param array $airports The array of airport data.
     * @param string $query The search query.
     * @return array Filtered airport data.
     */
    private function filterAirportsByQuery(array $airports, string $query): array
    {
        $arr = [];

        foreach ($airports as $aKey => $airport) {
            if ($this->matchesQuery($query, $airport, $aKey)) {
                $arr[$aKey] = $airport;
            }
        }

        return $arr;
    }

    /**
     * Check if an airport entry matches the search query.
     *
     * @param string $query The search query.
     * @param array $airport The airport data.
     * @param string $aKey The airport key.
     * @return bool True if there is a match, false otherwise.
     */
    private function matchesQuery(string $query, array $airport, string $aKey): bool
    {
        $searchableFields = [
            $airport['airportName']['en'] ?? null,
            $airport['airportName']['ru'] ?? null,
            $airport['country']['en']     ?? null,
            $airport['country']['ru']     ?? null,
            $airport['cityName']['en']    ?? null,
            $airport['cityName']['ru']    ?? null,
            $aKey,
        ];

        $searchableFields = array_filter($searchableFields);

        $lowerQuery = mb_strtolower($query);
        $pattern    = '/^' . preg_quote($lowerQuery, '/') . '/iu';

        foreach ($searchableFields as $field) {
            if (preg_match($pattern, mb_strtolower($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Group airports by country and city.
     *
     * @param array $arr The array of airport data.
     * @return array Grouped airport data.
     */
    private function groupAirports(array $arr): array
    {
        $results = [];
        $result = collect($arr)->groupBy('country');

        // Remove entries with non-Cyrillic characters in country names
        foreach ($result as $arrKey => $item) {
            if (preg_match("/[^а-яё -]/iu", $arrKey)) {
                unset($result[$arrKey]);
            }
        }

        foreach ($result as $key => $res) {
            $result[$key] = collect($res)->groupBy('cityName');

            // Remove entries with non-Cyrillic characters in city names
            foreach ($result[$key] as $key2 => $item) {
                if (!preg_match("/[а-яё]/iu", $key2)) {
                    unset($result[$key][$key2]);
                }
            }
        }

        foreach ($result as $countryKey => $cities) {
            $country = $countryKey;
            foreach ($cities as $cityKey => $airports) {
                $parent = $cityKey . ', ' . $country;
                foreach ($airports as $airport) {
                    if (!empty($airport['area'])) {
                        $results[$parent]['citycode'] = $airport['area']['code'];
                    }

                    $airport['name'] = $airport['airportName'] ?? $airport['cityName'];

                    $fieldsToUnset = ['timezone', 'lat', 'lng', 'city', 'area', 'country', 'airportName', 'cityName'];
                    foreach ($fieldsToUnset as $field) {
                        unset($airport[$field]);
                    }

                    $results[$parent]['airports'][] = $airport;
                }

            }
        }

        return $results;
    }

    /**
     * Get city information by city code.
     *
     * @param string $cityCode The 3-character city code to search for.
     *
     * @return array|null An array containing the airport information if found, or null if not found.
     */
    public function getCityByCode(string $cityCode): ?array
    {
        $airports = $this->airportDataRepository->getAllAirports();
        $cities = $this->airportDataRepository->getAllCities();

        $foundCity = $cities[$cityCode] ?? null;

        return $foundCity ?? [
                'name' => [
                    'ru' => $airports[$cityCode]['cityName']['ru'],
                    'en' => $airports[$cityCode]['cityName']['en']
                ],
                'country' => $airports[$cityCode]['country']
            ];
    }
}
