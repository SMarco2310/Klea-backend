<?php

namespace App\Policies;

use App\Models\Applications;
use App\Models\User;

class ApplicationsPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Applications $applications): bool
    {
        return $applications->tenant_id === $user->current_tenant_id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Applications $applications): bool
    {
        return $applications->tenant_id === $user->current_tenant_id;
    }

    public function delete(User $user, Applications $applications): bool
    {
        return $applications->tenant_id === $user->current_tenant_id;
    }
}
