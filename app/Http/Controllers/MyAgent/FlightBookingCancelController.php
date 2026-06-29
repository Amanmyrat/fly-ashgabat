<?php

namespace App\Http\Controllers\MyAgent;

use App\Http\Controllers\BaseController;
use App\Http\Requests\MyAgent\FlightBookingCancelRequest;
use App\Http\Resources\FlightBookingResource;
use App\Services\MyAgent\FlightBookingCancelService;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightBookingCancelController extends BaseController
{
    public function __construct(
        protected FlightBookingCancelService $flightBookingCancelService
    ) {
    }

    /**
     * Cancel a MyAgent flight booking
     *
     * @localizationHeader
     *
     * @param FlightBookingCancelRequest $request
     * @return JsonResponse
     */
    public function cancel(FlightBookingCancelRequest $request): JsonResponse
    {
        try {
            $response = $this->flightBookingCancelService->cancel($request->getBooking());

            return response()->json([
                'success' => true,
                'data' => new FlightBookingResource($response['booking']),
            ]);
        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }
}
