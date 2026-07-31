<?php

namespace App\Policies;

use App\Models\Features;
use App\Models\User;

class FeaturesPolicy
{
    protected function belongsToCurrentTenant(User $user, Features $features): bool
    {
        return $features->application->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Features $features): bool
    {
        return $this->belongsToCurrentTenant($user, $features);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Features $features): bool
    {
        return $this->belongsToCurrentTenant($user, $features);
    }

    public function delete(User $user, Features $features): bool
    {
        return $this->belongsToCurrentTenant($user, $features);
    }
}
