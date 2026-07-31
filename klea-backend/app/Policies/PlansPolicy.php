<?php

namespace App\Policies;

use App\Models\Plans;
use App\Models\User;

class PlansPolicy
{
    protected function belongsToCurrentTenant(User $user, Plans $plans): bool
    {
        return $plans->application->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plans $plans): bool
    {
        return $this->belongsToCurrentTenant($user, $plans);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Plans $plans): bool
    {
        return $this->belongsToCurrentTenant($user, $plans);
    }

    public function delete(User $user, Plans $plans): bool
    {
        return $this->belongsToCurrentTenant($user, $plans);
    }
}
