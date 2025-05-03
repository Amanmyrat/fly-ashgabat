<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Return a JSON error response
     *
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['error' => $message], $statusCode);
    }
}
