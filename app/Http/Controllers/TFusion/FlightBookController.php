<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightBookRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\FlightBookingResource;
use App\Jobs\CheckBookingStatusJob;
use App\Jobs\StartBookingJob;
use App\Services\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class FlightBookController extends BaseController
{
    public function __construct(protected FlightBookService $flightBookService)
    {
    }

    /**
     * Book a flight
     *
     * @param FlightBookRequest $request
     * @return JsonResponse
     */
    public function book(FlightBookRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $user = $this->getAuthenticatedUser();

        if ($validatedData['payment_type'] === 'balance' && !$user) {
            return $this->errorResponse('You must be logged in to use balance payment.', 403);
        }

        try {
            // Step 1: Process Details
            $response = $this->flightBookService->processDetails($validatedData, $user);
            if (!$response['success']) {
                return response()->json($response, 400);
            }

            // Step 2: Start Booking if balance payment
            if ($validatedData['payment_type'] === 'balance') {
                StartBookingJob::dispatch($response['booking']);

                // Step 3: Dispatch the check booking status job
                CheckBookingStatusJob::dispatch($response['booking'])->delay(now()->addSeconds(5));
            }

            return response()->json(['data' => [
                'success' => true,
                'booking_reference' => $response['booking']['booking_reference'],
            ]]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

    }

    /**
     * Get booking details
     *
     * @param string $bookId
     * @return JsonResponse
     */
    public function details(string $bookId): JsonResponse
    {
        return response()->json($this->flightBookService->getBookingDetails($bookId));
    }

    /**
     * Get bookings
     *
     * @return JsonResponse
     */
    public function getBookings(): JsonResponse
    {
        $bookings = Auth::user()->flightBookings()
            ->with('tickets')
            ->latest()
            ->get();
        return response()->json(['data' => $bookings]);
    }
}
