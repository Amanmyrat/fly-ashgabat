<?php
namespace App\Repositories;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * AirportDataRepository class.
 *
 * This class handles the retrieval of various geo data related to airports.
 * It provides methods to access data about airports, countries, airlines,
 * airCraft, and metropolitan areas. The data is retrieved from JSON files.
 */
class AirportDataRepository implements AirportDataRepositoryInterface
{
    /**
     * Retrieve all airports' data.
     *
     * Reads the airports.json file, decodes the JSON content, and returns it as an array.
     *
     * @return array An array of airports data.
     */
    public function getAllAirports(): array
    {
        try {
            return json_decode(File::get(public_path('/geodata/airports.json')), true);
        } catch (FileNotFoundException $e) {
            Log::error("Airports data file not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all countries' data.
     *
     * Reads the countries.json file, decodes the JSON content, and returns it as an array.
     *
     * @return array An array of countries data.
     */
    public function getAllCountries(): array
    {
        try {
            return json_decode(File::get(public_path('/geodata/countries.json')), true);
        } catch (FileNotFoundException $e) {
            Log::error("Countries data file not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all airlines' data.
     *
     * Reads the airlines.json file, decodes the JSON content, and returns it as an array.
     *
     * @return array An array of airlines data.
     */
    public function getAllAirlines(): array
    {
        try {
            return json_decode(File::get(public_path('/geodata/airlines.json')), true);
        } catch (FileNotFoundException $e) {
            Log::error("Airlines data file not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all aircraft data.
     *
     * Reads the aircraft.json file, decodes the JSON content, and returns it as an array.
     *
     * @return array An array of aircraft data.
     */
    public function getAllAirCrafts(): array
    {
        try {
            return json_decode(File::get(public_path('/geodata/aircraft.json')), true);
        } catch (FileNotFoundException $e) {
            Log::error("AirCrafts data file not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all metropolitan areas' data.
     *
     * Reads the metropolitanAreas.json file, decodes the JSON content, and returns it as an array.
     *
     * @return array An array of metropolitan areas data.
     */
    public function getAllCities(): array
    {
        try {
            return json_decode(File::get(public_path('/geodata/metropolitanAreas.json')), true);
        } catch (FileNotFoundException $e) {
            Log::error("City data file not found: " . $e->getMessage());
            return [];
        }
    }
}
