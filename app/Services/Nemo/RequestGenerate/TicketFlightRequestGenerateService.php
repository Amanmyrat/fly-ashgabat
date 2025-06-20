<?php

namespace App\Services\Nemo\RequestGenerate;

class TicketFlightRequestGenerateService
{
    /**
     * Generate a Ticket Flight Request.
     *
     * This function generates a Ticket Flight Request based on the provided POST request data.
     *
     * @param array $postRequest The POST request data containing ticketing parameters.
     * @return array The Ticket Flight Request.
     */
    public function generateTicketFlightRequest(array $postRequest): array
    {
        // Initialize the Ticket Flight Request with required fields
        $ticketRequest = $this->initializeRequest();

        // Set BookID based on the provided 'book_id' parameter
        $this->setBookID($ticketRequest, $postRequest);

        // Set RequestorTags from the class property
        $this->setRequestorTags($ticketRequest);

        // Set DataItem for Markup
        $this->setDataItemMarkup($ticketRequest);

        // Return the generated Ticket Flight Request
        return $ticketRequest;
    }

    /**
     * Initialize the Ticket Flight Request.
     *
     * @return array The initialized request.
     */
    private function initializeRequest(): array
    {
        return [
            'Ticket_2_2' => [
                'Request' => [
                    'Requisites' => [
                        'AuthToken' => config('nemo.auth_token'),
                    ],
                    'UserID' => config('nemo.user_id'),
                    'RequestBody' => [],
                ],
            ],
        ];
    }

    /**
     * Set BookID in the request.
     *
     * @param array $request The request to update.
     * @param array $postRequest The POST request data.
     */
    private function setBookID(array &$request, array $postRequest)
    {
        $request['Ticket_2_2']['Request']['RequestBody']['BookID'] = $postRequest['book_id'];
    }

    /**
     * Set RequestorTags in the request.
     *
     * @param array $request The request to update.
     */
    private function setRequestorTags(array &$request)
    {
        $request['Ticket_2_2']['Request']['RequestBody']['RequestorTags'] = config('nemo.tags');
    }

    /**
     * Set DataItem for Markup in the request.
     *
     * @param array $request The request to update.
     */
    private function setDataItemMarkup(array &$request)
    {
        //TODO uncomment this and remove other one on production
//        $ticketRequest['Ticket_2_2']['Request']['RequestBody']['DataItems']['DataItem'][] =
//            [
//                'ID' => 0,
//                'Type' => 'FOP',
//                'FOPInfo' => [
//                    'FOPs' => [
//                        'FOP' => [
//                            'Type' => 'CA'
//                        ]
//                    ],
//                ]
//            ];
        $request['Ticket_2_2']['Request']['RequestBody']['DataItems']['DataItem'][] =
            [
                'ID' => 0,
                'Type' => 'Markup',
                'Markup' => [
                    'MarkupValue' => [
                        'Amount' => 1,
                        'Currency' => 'USD',
                    ],
                ],
            ];
    }
}
