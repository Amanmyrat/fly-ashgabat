<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\UserResource;
use Auth;
use Throwable;

class UserController extends Controller
{
    /**
     * Get user
     *
     * @return UserResource
     */
    public function show(): UserResource
    {
        return new UserResource(Auth::user());
    }

    /**
     * Update profile
     *
     * @param UserUpdateRequest $request
     * @return UserResource
     * @throws Throwable
     */
    public function update(UserUpdateRequest $request): UserResource
    {
        $user = Auth::user();
        $user->update($request->validated());
        return new UserResource($user);
    }

}
