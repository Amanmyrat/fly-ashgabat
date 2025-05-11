<?php

namespace App\Services\TravelFusion\Requests;

use function Psl\Str\uppercase;
use App\Services\IpGeolocationService;
use App\Repositories\AirportDataRepositoryInterface;

class StartRoutingRequestBuilder
{
    protected array $data;
    protected IpGeolocationService $ipGeolocationService;
    protected array $airports;
    protected array $cities;

    public function __construct(
        array $validatedData,
        IpGeolocationService $ipGeolocationService,
        AirportDataRepositoryInterface $airportDataRepository
    ) {
        $this->data = $validatedData;
        $this->ipGeolocationService = $ipGeolocationService;
        $this->airports = $airportDataRepository->getAllAirports();
        $this->cities = $airportDataRepository->getAllCities();
    }

    public function build(): array
    {
        $requestData = [
            'StartRouting' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'Mode' => 'plane',
                'ProductType' => 'plane',
                'Origin' => $this->formatLocation($this->data['departure_code']),
                'Destination' => $this->formatLocation($this->data['arrival_code']),
                'OutwardDates' => [
                    'DateOfSearch' => date('d/m/Y-H:i', strtotime($this->data['departure_date'])),
                ],
                'MaxChanges' => $this->data['is_direct_flight'] ? 1 : 4,
                'MaxHops' => $this->data['is_direct_flight'] ? 1 : 4,
                'TravellerList' => [
                    'Traveller' => [],
                ],
                'Timeout' => 40,
                'IncrementalResults' => 'true',
                'BookingProfile' => [
                    'CustomSupplierParameterList' => [
                        'CustomSupplierParameter' => [
                            [
                                'Name' => 'IncludeStructuredFeatures',
                                'Value' => 'y',
                            ],
                            [
                                'Name' => 'PreferredLanguage',
                                'Value' => uppercase(app()->getLocale()),
                            ],
                            [
                                'Name' => 'EndUserIPAddress',
                                'Value' => $this->data['meta']['end_user_ip_address'] ?? '',
                            ],
                            [
                                'Name' => 'EndUserBrowserAgent',
                                'Value' => $this->data['meta']['end_user_browser_agent'] ?? '',
                            ],
                            [
                                'Name' => 'EndUserDeviceMACAddress',
                                'Value' => $this->data['meta']['end_user_device_mac_address'] ?? '',
                            ],
                            [
                                'Name' => 'RequestOrigin',
                                'Value' => $this->getRequestOrigin(),
                            ],
                            [
                                'Name' => 'PointOfSale',
                                'Value' => 'TM',
                            ],
                            [
                                'Name' => 'CountryOfTheUser',
                                'Value' => $this->getCountryOfTheUser(),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Add TravelClass if necessary
        if ($this->data['class_type'] === 'business') {
            $requestData['StartRouting']['TravelClass'] = 'Business';
        } elseif ($this->data['class_type'] === 'economy') {
            $requestData['StartRouting']['TravelClass'] = 'Economy With Restrictions';
        }

        // Add ReturnDates if flight type is round-trip
        if ($this->data['flight_type'] === 'round-trip') {
            $requestData['StartRouting']['ReturnDates'] = [
                'DateOfSearch' => date('d/m/Y-H:i', strtotime($this->data['arrival_date'])),
            ];
        }

        $adultsCount = $this->data['adults_count'] ?? 0;
        $childrenCount = $this->data['children_count'] ?? 0;
        $infantsCount = $this->data['infants_count'] ?? 0;

        $requestData['StartRouting']['TravellerList'] = [];

        for ($i = 0; $i < $adultsCount; $i++) {
            $requestData['StartRouting']['TravellerList'][] = ['Traveller' => ['Age' => '30']];
        }
        for ($i = 0; $i < $childrenCount; $i++) {
            $requestData['StartRouting']['TravellerList'][] = ['Traveller' => ['Age' => '7']];
        }
        for ($i = 0; $i < $infantsCount; $i++) {
            $requestData['StartRouting']['TravellerList'][] = ['Traveller' => ['Age' => '0']];
        }

        return $requestData;
    }

    protected function getRequestOrigin(): string
    {
        $ip = $this->data['meta']['end_user_ip_address'] ?? '';
        $country = $this->getCountryOfTheUser();
        return sprintf('%s-flyashgabat.com', $country);
    }

    protected function getCountryOfTheUser(): string
    {
        $ip = $this->data['meta']['end_user_ip_address'] ?? '';
        return $this->ipGeolocationService->getCountryCode($ip);
    }

    protected function formatLocation(string $code): array
    {
        // Check if it's an airport code
        if (isset($this->airports[$code])) {
            return [
                'Descriptor' => $code,
                'Type' => 'airportcode',
                'Radius' => 1000
            ];
        }

        // Check if it's a city code
        if (isset($this->cities[$code])) {
            return [
                'Descriptor' => $code,
                'Type' => 'airportgroup'
            ];
        }

        // If we can't determine the type, default to airportcode with radius
        return [
            'Descriptor' => $code,
            'Type' => 'airportcode',
            'Radius' => 1000
        ];
    }
}
