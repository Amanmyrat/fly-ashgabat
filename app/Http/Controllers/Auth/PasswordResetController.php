<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(protected PasswordResetService $passwordResetService)
    {
    }

    /**
     * Request password reset email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestReset(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $this->passwordResetService->sendResetEmail($request->email);

        return response()->json(['message' => 'Reset code sent to your email']);
    }

    /**
     * Verify password reset code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|integer'
        ]);

        if (!$this->passwordResetService->verifyResetCode($request->email, $request->code)) {
            return response()->json(['message' => 'Invalid or expired code'], 404);
        }

        return response()->json(['message' => 'Code is valid']);
    }

    /**
     * Reset password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
                'email' => 'required|email',
                'code' => 'required|string',
                'password' => 'required|confirmed|min:8',
            ]
        );

        if (!$this->passwordResetService->verifyResetCode($request->email, $request->code)) {
            return response()->json(['message' => 'Invalid or expired code'], 404);
        }

        $this->passwordResetService->resetUserPassword($request->email, $request->password);

        return response()->json(['message' => 'Password has been reset']);
    }

}
