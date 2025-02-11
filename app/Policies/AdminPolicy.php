<?php

namespace App\Policies;

use App\Enum\AdminRole;
use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminPolicy
{
    use HandlesAuthorization;

    public function before(Admin $user): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }

    public function viewAny(Admin $user): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN || $user->role === AdminRole::OPERATOR;
    }

    public function view(Admin $user, Admin $model): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }

    public function create(Admin $user): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }

    public function update(Admin $user, Admin $model): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }

    public function delete(Admin $user, Admin $model): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }

    public function deleteAny(Admin $user): bool
    {
        return $user->role === AdminRole::SUPER_ADMIN;
    }
}

