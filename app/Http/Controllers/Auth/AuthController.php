<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\EmailVerificationRequest;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\RegistrationRequest;
use App\Models\User;
use App\Notifications\CustomEmailVerification;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * User registration
     *
     * Handle the incoming registration request.
     * @param RegistrationRequest $request
     * @return JsonResponse
     */
    public function register(RegistrationRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        if (array_key_exists('error', $result)) {
            return response()->json(['data' => $result], 500);
        }

        return response()->json(['data' => $result], 201);
    }

    /**
     * User login
     *
     * Handle the incoming login request.
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->authenticate($request->validated());

        if (array_key_exists('error', $result)) {
            return response()->json(['data' => $result], 401);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * User email verification
     *
     * Handle email verification.
     * @param EmailVerificationRequest $request
     * @return RedirectResponse
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->away(config('mail.urls.login_redirect'));
    }

    /**
     * User password change
     *
     * Handle user password changing.
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->authService->changePassword($user, $request->old_password, $request->new_password);

        if (array_key_exists('error', $result)) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

}
