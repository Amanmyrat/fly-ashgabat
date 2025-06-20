<?php

namespace App\Services\Nemo\RequestGenerate;

use JetBrains\PhpStorm\ArrayShape;

class BookFlightRequestGenerateService
{
    /**
     * Generate a book flight request based on the provided input data.
     *
     * @param array $postRequest The input data for the book flight request.
     *
     * @return array The generated book flight request.
     */
    #[ArrayShape(['BookFlight_2_2' => "array[]"])]
    public function generateBookFlightRequest(array $postRequest): array
    {
        $bookFlightRequest = $this->initializeRequest();

        $bookFlightRequest['BookFlight_2_2']['Request']['RequestBody']['FlightID'] = $postRequest['flight_id'];

        $this->populateTravellers($bookFlightRequest, $postRequest['travelers']);
        $this->populateDataItems($bookFlightRequest, $postRequest['data_items']);

        $this->finalizeRequest($bookFlightRequest);

        return $bookFlightRequest;
    }

    /**
     * Initialize the book flight request structure.
     *
     * @return array The initialized book flight request.
     */
    #[ArrayShape(['BookFlight_2_2' => "array[]"])]
    private function initializeRequest(): array
    {
        return [
            'BookFlight_2_2' => [
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

    /**
     * Populate the book flight request with traveler information.
     *
     * @param array  $request   The book flight request to update.
     * @param mixed  $travelers The traveler information to populate.
     */
    private function populateTravellers(array &$request, mixed $travelers)
    {
        $travelers = is_string($travelers) ? json_decode($travelers, true) : $travelers;
        foreach ($travelers as $traveler) {
            $request['BookFlight_2_2']['Request']['RequestBody']['Travellers'][] = $traveler;
        }
    }

    /**
     * Populate the book flight request with data items.
     *
     * @param array  $request    The book flight request to update.
     * @param mixed  $dataItems The data items to populate.
     */
    private function populateDataItems(array &$request, mixed $dataItems)
    {
        $dataItems = is_string($dataItems) ? json_decode($dataItems, true) : $dataItems;

        foreach ($dataItems as $data_item) {
            $request['BookFlight_2_2']['Request']['RequestBody']['DataItems'][] = $data_item;
        }
    }

    /**
     * Finalize the book flight request by adding remaining information.
     *
     * @param array $request The book flight request to update.
     */
    private function finalizeRequest(array &$request)
    {
        $request['BookFlight_2_2']['Request']['RequestBody']['RequestorTags'] = config('nemo.tags');
    }
}
