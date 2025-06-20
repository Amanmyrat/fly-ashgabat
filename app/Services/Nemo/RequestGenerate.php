<?php

namespace App\Services\Nemo;

use App\Services\Nemo\RequestGenerate\AdditionalOperationsRequestGenerateService;
use App\Services\Nemo\RequestGenerate\BookFlightRequestGenerateService;
use App\Services\Nemo\RequestGenerate\CancelBookRequestGenerateService;
use App\Services\Nemo\RequestGenerate\FlightRepricingRequestGenerateService;
use App\Services\Nemo\RequestGenerate\SearchRequestGenerateService;
use App\Services\Nemo\RequestGenerate\TicketFlightRequestGenerateService;
use App\Services\Nemo\RequestGenerate\UpdateBookRequestGenerateService;

class RequestGenerate
{
    public function __construct(
        protected SearchRequestGenerateService $searchRequestGenerateService,
        protected BookFlightRequestGenerateService $bookFlightRequestGenerateService,
        protected FlightRepricingRequestGenerateService $flightRepricingRequestGenerateService,
        protected AdditionalOperationsRequestGenerateService $additionalOperationsRequestGenerateService,
        protected TicketFlightRequestGenerateService $ticketFlightRequestGenerateService,
        protected CancelBookRequestGenerateService $cancelBookRequestGenerateService,
        protected UpdateBookRequestGenerateService $updateBookRequestGenerateService,
    )
    {
    }

    /**
     * Generate a search request for flight information.
     *
     * @param array $postRequest The input data for the search request.
     *
     * @return array The generated search request.
     */
    public function generateSearchRequest(array $postRequest): array
    {
        return $this->searchRequestGenerateService->generateSearchRequest($postRequest);
    }

    /**
     * Generate a book flight request based on the provided input data.
     *
     * @param array $postRequest The input data for the book flight request.
     *
     * @return array The generated book flight request.
     */
    public function generateBookFlightRequest(array $postRequest): array
    {
        return $this->bookFlightRequestGenerateService->generateBookFlightRequest($postRequest);
    }

    /**
     * Generate a flight repricing request based on the provided input data.
     *
     * @param array $postRequest The input data for the flight repricing request.
     *
     * @return array The generated flight repricing request.
     */
    public function generateFlightRepricingRequest(array $postRequest): array
    {
        return $this->flightRepricingRequestGenerateService->generateFlightRepricingRequest($postRequest);
    }

    /**
     * Generate an additional operations request for a flight based on the provided input data.
     *
     * @param array $postRequest The input data for the additional operations request.
     *
     * @return array The generated additional operations request.
     */
    public function generateAdditionalOperationsRequest(array $postRequest): array
    {
        return $this->additionalOperationsRequestGenerateService->generateAdditionalOperationsRequest($postRequest);
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

    /**
     * Generate an Update Book Request.
     *
     * This function generates an Update Book Request based on the provided book ID.
     *
     * @param int $bookId
     * @return array The Update Book Request.
     */
    public function generateUpdateBookRequest(int $bookId): array
    {
        return $this->updateBookRequestGenerateService->generateUpdateBookRequest($bookId);
    }
}
