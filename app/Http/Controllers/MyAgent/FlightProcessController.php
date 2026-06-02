<?php

namespace App\Http\Controllers\MyAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyAgent\FlightProcessDetailsRequest;
use App\Services\MyAgent\FlightProcessService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlightProcessController extends Controller
{
    public function __construct(
        protected FlightProcessService $flightProcessService
    ) {
    }

    /**
     * Process details of flight
     *
     * @param FlightProcessDetailsRequest $request
     * @return JsonResponse
     */
    public function processDetails(FlightProcessDetailsRequest $request): JsonResponse
    {
        try {
            $result = $this->flightProcessService->processFlight($request->validated());

            return response()->json([
                'data' => $result,
            ]);
        } catch (Exception $exception) {
            Log::channel('myagent')->error('Flight process controller error ' . json_encode([
                    'message' => $exception->getMessage(),
                    'request' => $request->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'data' => [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ],
            ], 400);
        }
    }
}
