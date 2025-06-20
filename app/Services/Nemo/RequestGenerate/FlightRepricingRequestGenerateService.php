<?php

namespace App\Services\Nemo\RequestGenerate;

class FlightRepricingRequestGenerateService
{
    /**
     * Generate a flight repricing request based on the provided input data.
     *
     * @param array $postRequest The input data for the flight repricing request.
     *
     * @return array The generated flight repricing request.
     */
    public function generateFlightRepricingRequest(array $postRequest): array
    {
        $flightRepricingRequest = $this->initializeRequest();

        $flightRepricingRequest['FlightRepricing']['Request']['RequestBody']['FlightID'] = $postRequest['flight_id'];

        return $flightRepricingRequest;
    }

    /**
     * Initialize the flight repricing request structure.
     *
     * @return array The initialized flight repricing request.
     */
    private function initializeRequest(): array
    {
        return [
            'FlightRepricing' => [
                'Request' => [
                    'Requisites' => [
                        'AuthToken' => config('nemo.auth_token'),
                    ],
                    'UserID' => config('nemo.user_id'),
                    'RequestType' => 'U',
                    'RequestBody' => []
                ]
            ]
        ];
    }
}
