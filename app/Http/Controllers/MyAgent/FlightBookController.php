<?php

namespace App\Http\Controllers\MyAgent;

use App\Enum\PaymentType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\MyAgent\FlightBookRequest;
use App\Http\Resources\FlightBookingResource;
use App\Services\MyAgent\FlightBookService;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightBookController extends BaseController
{
    public function __construct(
        protected FlightBookService $flightBookService
    ) {
    }

    /**
     * Process flight booking and create initial booking record
     *
     * @param FlightBookRequest $request
     * @return JsonResponse
     * @throws Exception
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
                return response()->json($response, $response['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'data' => new FlightBookingResource($response['booking']),
            ]);
        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }
}
