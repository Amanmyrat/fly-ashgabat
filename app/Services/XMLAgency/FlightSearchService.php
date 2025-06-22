<?php

namespace App\Services\XMLAgency;

use App\Services\XMLAgency\Requests\AeroSearchRequestBuilder;

class FlightSearchService
{
    protected XMLAgencyService $xmlAgencyService;

    public function __construct(XMLAgencyService $xmlAgencyService)
    {
        $this->xmlAgencyService = $xmlAgencyService;
    }

    public function search(array $validatedData): array
    {
        $requestBuilder = new AeroSearchRequestBuilder($validatedData);
        $requestData = $requestBuilder->build();

        $response = $this->xmlAgencyService->sendRequest($requestData, 'AeroSearch');

        return $this->processResponse($response);
    }

    private function processResponse(array $response): array
    {
        // Simple response processing - you can expand this later
        return [
            'success' => true,
            'data' => $response,
            'flights' => []
        ];
    }
}
