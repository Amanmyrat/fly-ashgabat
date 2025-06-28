<?php

namespace App\Http\Controllers\XMLAgency;

use App\Enum\PaymentType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\XMLAgency\FlightBookRequest;
use App\Http\Resources\FlightBookingResource;
use App\Services\XMLAgency\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightBookController extends BaseController
{
    public function __construct(
        protected FlightBookService $flightBookService,
    ) {
    }

    /**
     * Process flight booking and create initial booking record
     *
     * @param FlightBookRequest $request
     * @return JsonResponse
     */
    public function processBooking(FlightBookRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $user = $this->getAuthenticatedUser();

        if ($validatedData['payment_type'] === PaymentType::BALANCE->value && !$user) {
            return $this->errorResponse('You must be logged in to use balance payment.', 403);
        }

        try {
            $response = $this->flightBookService->processBooking($validatedData, $user);
            if (!$response['success']) {
                return response()->json($response, 400);
            }

            return response()->json([
                'success' => true,
                'data' => new FlightBookingResource($response['booking'])
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
