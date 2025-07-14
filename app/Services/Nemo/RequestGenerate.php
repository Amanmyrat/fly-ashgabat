<?php

namespace App\Services\Nemo;

use App\Services\Nemo\RequestGenerate\CancelBookRequestGenerateService;
use App\Services\Nemo\RequestGenerate\TicketFlightRequestGenerateService;

class RequestGenerate
{
    public function __construct(
        protected TicketFlightRequestGenerateService $ticketFlightRequestGenerateService,
        protected CancelBookRequestGenerateService $cancelBookRequestGenerateService,
    )
    {
    }


    /**
     * Generate a Ticket Flight Request.
     *
     * This function generates a Ticket Flight Request based on the provided POST request data.
     *
     * @param array $postRequest
     * @return array The Ticket Flight Request.
     */
    public function generateTicketFlightRequest(array $postRequest): array
    {
        return $this->ticketFlightRequestGenerateService->generateTicketFlightRequest($postRequest);
    }

    /**
     * Generate a Cancel Book Request.
     *
     * This function generates a Cancel Book Request based on the provided book ID.
     *
     * @param int $bookId
     * @return array The Cancel Book Request.
     */
    public function generateCancelBookRequest(int $bookId): array
    {
        return $this->cancelBookRequestGenerateService->generateCancelBookRequest($bookId);
    }
}
