<?php

namespace App\Services\Nemo\RequestGenerate;

class AdditionalOperationsRequestGenerateService
{
    /**
     * Generate an additional operations request for a flight based on the provided input data.
     *
     * @param array $postRequest The input data for the additional operations request.
     *
     * @return array The generated additional operations request.
     */
    public function generateAdditionalOperationsRequest(array $postRequest): array
    {
        $flightRepricingRequest = $this->initializeRequest();

        $flightRepricingRequest['AdditionalOperations_1_2']['Request']['RequestBody']['ObjectForOperations']['FlightID'] = $postRequest['flight_id'];
        $flightRepricingRequest['AdditionalOperations_1_2']['Request']['RequestBody']['Operations']['Operation'][] = $postRequest['operation'];

        $flightRepricingRequest['AdditionalOperations_1_2']['Request']['RequestBody']['RequestorTags'] = config('nemo.tags');

        return $flightRepricingRequest;
    }

    /**
     * Initialize the additional operations request structure.
     *
     * @return array The initialized additional operations request.
     */
    private function initializeRequest(): array
    {
        return [
            'AdditionalOperations_1_2' => [
                'Request' => [
                    'Requisites' => [
                        'AuthToken' => config('nemo.auth_token'),
                    ],
                    'UserID' => config('nemo.user_id'),
                    'RequestBody' => []
                ]
            ]
        ];
    }
}
