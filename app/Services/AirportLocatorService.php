<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;
use Illuminate\Support\Facades\App;

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

        // --- New logic: prioritize code-matching results ---
        $codeMatches = [];
        $otherMatches = [];
        $upperQuery = strtoupper($query);
        foreach ($arr as $aKey => $airport) {
            $isCodeMatch = false;
            if (strtoupper($aKey) === $upperQuery) {
                $isCodeMatch = true;
            } elseif (isset($airport['area']['code']) && strtoupper($airport['area']['code']) === $upperQuery) {
                $isCodeMatch = true;
            }
            if ($isCodeMatch) {
                $codeMatches[$aKey] = $airport;
            } else {
                $otherMatches[$aKey] = $airport;
            }
        }
        // Merge code matches first, then others
        $orderedArr = $codeMatches + $otherMatches;
        // --- End new logic ---
        return $this->groupAirports($orderedArr);
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
                : $this->getLocalizedString($airport['cityName']) . ', ' . $this->getLocalizedString($countries[$airport['country']]['name']);

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
     * Get a localized string value based on the current application locale with fallback to English.
     *
     * @param array $data Array containing localized strings with language keys.
     * @return string The localized string.
     */
    private function getLocalizedString(array $data): string
    {
        $locale = App::getLocale();
        return $data[$locale] ?? $data['ru'] ?? '';
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
            ? $this->getLocalizedString($airport['airportName']) . ', ' .
              $this->getLocalizedString($airport['cityName']) . ', ' .
              $this->getLocalizedString($countries[$airport['country']]['name'])
            : $this->getLocalizedString($airport['cityName']) . ', ' .
              $this->getLocalizedString($countries[$airport['country']]['name']);
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
            $airport['area']['code'] ?? null, // City code
            $airport['airportName']['en'] ?? null,
            $airport['airportName']['ru'] ?? null,
            $airport['country']['en']     ?? null,
            $airport['country']['ru']     ?? null,
            $airport['cityName']['en']    ?? null,
            $airport['cityName']['ru']    ?? null,
            $aKey, // Airport code
        ];

        $searchableFields = array_filter($searchableFields);

        $lowerQuery = mb_strtolower($query);
        $pattern    = '/^' . preg_quote($lowerQuery, '/') . '/iu';

        // Check for exact code match first
        if (strtoupper($query) === strtoupper($aKey) ||
            (isset($airport['area']['code']) && strtoupper($query) === strtoupper($airport['area']['code']))) {
            return true;
        }

        // Check for partial matches in all fields
        foreach ($searchableFields as $field) {
            if (preg_match($pattern, mb_strtolower($field))) {
                return true;
            }
        }

        // Check for city code matches in the middle of the string
        if (isset($airport['area']['code']) &&
            strpos(strtoupper($airport['area']['code']), strtoupper($query)) !== false) {
            return true;
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
        $locale = App::getLocale();
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
            foreach ($cities as $cityKey => $airports) {
                // Get localized city and country names for the parent key
                $cityName = '';
                $countryName = '';

                // Get the first airport to extract city and country names
                $firstAirport = $airports->first();
                if (!empty($firstAirport['cityName'])) {
                    $cityName = $this->getLocalizedString($firstAirport['cityName']);
                }

                if (!empty($firstAirport['country'])) {
                    $countryName = $this->getLocalizedString($firstAirport['country']);
                }

                $parent = $cityName . ', ' . $countryName;

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
