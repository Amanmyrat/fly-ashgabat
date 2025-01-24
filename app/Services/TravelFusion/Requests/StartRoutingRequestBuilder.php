<?php

namespace App\Services\TravelFusion\Requests;

class StartRoutingRequestBuilder
{
    protected array $data;

    public function __construct(array $validatedData)
    {
        $this->data = $validatedData;
    }

    public function build(): array
    {
        $requestData = [
            'StartRouting' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'Mode' => 'plane',
                'Origin' => [
                    'Descriptor' => $this->data['departure_code'],
                    'Type' => 'auto',
                ],
                'Destination' => [
                    'Descriptor' => $this->data['arrival_code'],
                    'Type' => 'auto',
                ],
                'OutwardDates' => [
                    'DateOfSearch' => date('d/m/Y-H:i', strtotime($this->data['departure_date'])),
                ],
                'MaxChanges' => $this->data['is_direct_flight'] ? 1 : 2,
                'MaxHops' => $this->data['is_direct_flight'] ? 2 : 4,
                'TravellerList' => [
                    'Traveller' => [],
                ],
                'Timeout' => 40,
                'IncrementalResults' => 'true',
                'BookingProfile' => [
                    'CustomSupplierParameterList' => [
                        'CustomSupplierParameter' => [
                            'Name' => 'IncludeStructuredFeatures',
                            'Value' => 'y',
                        ]
                    ]
                ]
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

}
