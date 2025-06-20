<?php

namespace App\Repositories;

interface AirportDataRepositoryInterface
{
    public function getAllAirports(): array;
    public function getAllCountries(): array;
    public function getAllCities(): array;
    public function getAllAirlines(): array;
    public function getAllAirCrafts(): array;
}
