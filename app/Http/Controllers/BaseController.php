<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{
    /**
     * Get authenticated user or null
     *
     * @return User|null
     */
    public function getAuthenticatedUser(): ?User
    {
        $user = Auth::guard('sanctum')->user();
        return $user instanceof User ? $user : null;
    }

    /**
     * Handle service calls with try-catch block
     *
     * @param callable $callback
     * @return JsonResponse
     */
    public function handleServiceCall(callable $callback): JsonResponse
    {
        try {
            $response = $callback();
            return $response['success']
                ? response()->json(['data' => $response])
                : response()->json($response, 400);
        } catch (Exception $e) {
            // Log the full exception details
            Log::error('Service call exception: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse($e, 500);
        }
    }

    /**
     * Return a JSON error response
     *
     * @param string|Exception $error
     * @param int $statusCode
     * @return JsonResponse
     */
    public function errorResponse($error, int $statusCode): JsonResponse
    {
        // If error is an Exception object, extract more information
        if ($error instanceof Exception) {
            $errorData = [
                'error' => $error->getMessage(),
            ];

            // In debug mode, include more detailed information
            if (config('app.debug')) {
                $errorData['debug'] = [
                    'exception' => get_class($error),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                    'trace' => $error->getTraceAsString(),
                ];
            }

            return response()->json($errorData, $statusCode);
        }

        // If error is just a string message
        return response()->json(['error' => $error], $statusCode);
    }
}
