<?php

namespace App\Services;

use App\Services\TravelFusion\Requests\ProcessDetailsRequestBuilder;
use App\Services\TravelFusion\Requests\ProcessTermsRequestBuilder;
use App\Services\TravelFusion\Requests\StartBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Exception;
use Illuminate\Http\Client\ConnectionException;

class FlightBookService
{

    public function __construct(
        protected TravelFusionService $travelFusionService,
    )
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function book(array $validatedData): array
    {
        // Step 1: ProcessDetails
        // TODO add baggage and luggage options to request
        $processDetailsRequest = (new ProcessDetailsRequestBuilder($validatedData))->build();
        $processDetailsResponse = $this->travelFusionService->sendRequest($processDetailsRequest);

        if (!isset($processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group'])) {
            return ['message' => 'No result(ProcessDetails) found'];
        }

        // Step 2: ProcessTerms
        $processTermsRequest = (new ProcessTermsRequestBuilder($validatedData))->build();
        $processTermsResponse = $this->travelFusionService->sendRequest($processTermsRequest, 'processTerms');

        if (!isset($processTermsResponse['ProcessTerms']['Router']['GroupList']['Group'])) {
            return ['message' => 'No result(ProcessTerms) found'];
        }

        $data = [
            'tf_booking_reference' => $processTermsResponse['ProcessTerms']['TFBookingReference'],
            'price' => $processTermsResponse['ProcessTerms']['Router']['GroupList']['Group']['Price']
        ];

        // Step 3: StartBooking
        $startBookingRequest = (new StartBookingRequestBuilder($data))->build();
        $startBookingResponse = $this->travelFusionService->sendRequest($startBookingRequest);

        return [
            true
        ];
    }

}
