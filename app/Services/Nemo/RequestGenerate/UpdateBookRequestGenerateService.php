<?php

namespace App\Services\Nemo\RequestGenerate;

class UpdateBookRequestGenerateService
{

    /**
     * Generate an Update Book Request.
     *
     * This function generates an Update Book Request based on the provided book ID.
     *
     * @param int $bookId The ID of the book to update.
     * @return array The Update Book Request.
     */
    public function generateUpdateBookRequest(int $bookId): array
    {
        // Initialize the Update Book Request with required fields
        $updateBook = $this->initializeUpdateBookRequest();

        // Set the update book data
        $this->setUpdateBookData($updateBook, $bookId);

        // Return the generated Update Book Request
        return $updateBook;
    }

    /**
     * Initialize the Update Book Request.
     *
     * @return array The initialized request.
     */
    private function initializeUpdateBookRequest(): array
    {
        return [
            'UpdateBook_2_2' => [
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
     * Set data for updating a book in the request.
     *
     * @param array $request The request to update.
     * @param int $bookId The ID of the book to update.
     */
    private function setUpdateBookData(array &$request, int $bookId): void
    {
        $request['UpdateBook_2_2']['Request']['RequestBody']['BookID'] = $bookId;
        $request['UpdateBook_2_2']['Request']['RequestBody']['FillEdDocContent'] = true;

        $request['UpdateBook_2_2']['Request']['RequestBody']['RequestorTags'] = config('nemo.tags');
    }
}
