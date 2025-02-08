<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlightBookRequest;
use App\Services\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightBookController extends Controller
{

    public function __construct(protected FlightBookService $flightBookService)
    {
    }

    /**
     * Book tfusion
     *
     * @param FlightBookRequest $request
     * @return JsonResponse
     */
    public function book(FlightBookRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $response = $this->flightBookService->book($validatedData);
            if (!$response['success']) {
                return new JsonResponse($response, 400);
            }
            return new JsonResponse([
                'data' => $response,
            ]);

        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check booking
     *
     * @param string $bookId
     * @return JsonResponse
     */
    public function check(string $bookId): JsonResponse
    {
        try {
            $response = $this->flightBookService->checkBooking($bookId);
            if (!$response['success']) {
                return new JsonResponse($response, 400);
            }
            return new JsonResponse([
                'data' => $response,
            ]);

        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Booking details
     *
     * @param string $bookId
     * @return JsonResponse
     */
    public function details(string $bookId): JsonResponse
    {
        try {
            $response = $this->flightBookService->bookingDetails($bookId);
            if (!$response['success']) {
                return new JsonResponse($response, 400);
            }
            return new JsonResponse([
                'data' => $response,
            ]);

        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
