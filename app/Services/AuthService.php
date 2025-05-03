<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Register a new user.
     *
     * @param array $data
     * @return array
     */
    public function register(array $data): array
    {
        try {
            $user = User::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'company' => $data['company'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $accessToken = $user->createToken('authToken')->plainTextToken;

            return ['user' => new UserResource($user), 'token' => $accessToken, 'message' => 'Registered successfully'];
        } catch (Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());

            return ['error' => 'Registration failed: '. $e->getMessage()];
        }
    }

    /**
     * Login user
     *
     * @param array $credentials
     * @return array
     */
    public function authenticate(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            return ['error' => 'Data entered incorrectly. Try again.'];
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'status' => 'success',
            'user' => new UserResource($user),
            'token' => $token,
        ];
    }

    /**
     * Change user password
     *
     * @param User $user
     * @param $oldPassword
     * @param $newPassword
     * @return array
     */
    public function changePassword(User $user, $oldPassword, $newPassword): array
    {
        if (!Hash::check($oldPassword, $user->password)) {
            return ['error' => 'The old password is incorrect.'];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        return ['success' => 'Password changed successfully.'];
    }
}
