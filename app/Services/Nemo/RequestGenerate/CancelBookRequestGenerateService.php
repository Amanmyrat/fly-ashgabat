<?php

namespace App\Services\Nemo\RequestGenerate;

class CancelBookRequestGenerateService
{
    /**
     * Generate a Cancel Book Request.
     *
     * This function generates a Cancel Book Request based on the provided book ID.
     *
     * @param int $bookId The ID of the book to cancel.
     * @return array The Cancel Book Request.
     */
    public function generateCancelBookRequest(int $bookId): array
    {
        // Initialize the Cancel Book Request with required fields
        $cancelBookRequest = $this->initializeCancelBookRequest();

        // Set BookID based on the provided book ID
        $this->setCancelBookID($cancelBookRequest, $bookId);

        // Return the generated Cancel Book Request
        return $cancelBookRequest;
    }

    /**
     * Initialize the Cancel Book Request.
     *
     * @return array The initialized request.
     */
    private function initializeCancelBookRequest(): array
    {
        return [
            'CancelBook' => [
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
     * Set BookID in the Cancel Book Request.
     *
     * @param array $request The request to update.
     * @param int $bookId The ID of the book to cancel.
     */
    private function setCancelBookID(array &$request, int $bookId)
    {
        $request['CancelBook']['Request']['RequestBody']['BookID'] = $bookId;
    }
}
