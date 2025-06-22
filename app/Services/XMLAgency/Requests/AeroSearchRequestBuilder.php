<?php

namespace App\Services\XMLAgency\Requests;

class AeroSearchRequestBuilder
{
    protected array $data;

    public function __construct(array $validatedData)
    {
        $this->data = $validatedData;
    }

    public function build(): array
    {
        $adults = $this->data['adults_count'] ?? 1;
        $children = $this->data['children_count'] ?? 0;
        $infants = $this->data['infants_count'] ?? 0;
        
        $flightClass = $this->mapFlightClass($this->data['class_type'] ?? 'economy');
        
        $departureDate = $this->formatDate($this->data['departure_date']);
        $departureCode = $this->data['departure_code'];
        $arrivalCode = $this->data['arrival_code'];

        // Build search flights array
        $searchFlights = [
            [
                'Date' => $departureDate,
                'IATAFrom' => $departureCode,
                'IATATo' => $arrivalCode,
            ]
        ];

        // Add return flight if round-trip
        if ($this->data['flight_type'] === 'round-trip' && !empty($this->data['arrival_date'])) {
            $returnDate = $this->formatDate($this->data['arrival_date']);
            $searchFlights[] = [
                'Date' => $returnDate,
                'IATAFrom' => $arrivalCode,
                'IATATo' => $departureCode,
            ];
        }

        $requestData = [
            'AeroSearch' => [
                'credentials' => [
                    'ApiLogin' => config('xmlagency.api_login'),
                    'ApiPassword' => config('xmlagency.api_password'),
                    'AuthExtendedData' => null,
                    'Currency' => config('xmlagency.currency'),
                    'DeviceId' => config('xmlagency.device_id'),
                    'Language' => config('xmlagency.language'),
                    'TokenGuid' => '00000000-0000-0000-0000-000000000000',
                ],
                'aeroSearchParams' => [
                    'Adults' => $adults,
                    'ExtendedParams' => null,
                    'FlightClass' => $flightClass,
                    'PartnerName' => null,
                    'SearchFlights' => ['SearchFlight' => $searchFlights],
                ],
            ],
        ];

        // Add children and infants if needed
        if ($children > 0) {
            $requestData['AeroSearch']['aeroSearchParams']['Childs'] = $children;
        }

        if ($infants > 0) {
            $requestData['AeroSearch']['aeroSearchParams']['Infants'] = $infants;
        }

        return $requestData;
    }

    private function mapFlightClass(string $classType): string
    {
        return match (strtolower($classType)) {
            'economy' => 'Econom',
            'business' => 'Business',
            'first' => 'First',
            default => 'Econom'
        };
    }

    private function formatDate(string $date): string
    {
        return date('d.m.Y', strtotime($date));
    }

    
} 