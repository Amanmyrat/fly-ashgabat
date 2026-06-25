<?php

namespace App\Http\Controllers\MyAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyAgent\FlightPickRequest;
use App\Services\MyAgent\FlightPickService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlightPickController extends Controller
{
    public function __construct(
        protected FlightPickService $flightPickService
    ) {
    }

    /**
     * Pick a flight recommendation and load booking requirements.
     *
     * @localizationHeader
     *
     * @param FlightPickRequest $request
     * @return JsonResponse
     */
    public function pick(FlightPickRequest $request): JsonResponse
    {
        try {
            $result = $this->flightPickService->pick($request->validated());

            return response()->json([
                'data' => $result,
            ]);
        } catch (Exception $exception) {
            Log::channel('myagent')->error('Flight pick controller error ' . json_encode([
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
