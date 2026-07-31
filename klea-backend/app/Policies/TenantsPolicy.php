<?php

namespace App\Policies;

use App\Models\Tenants;
use App\Models\User;

class TenantsPolicy
{
    /**
     * A user may act on a tenant only if they belong to it
     * (not just if it's their current tenant — they may belong to several).
     */
    protected function belongsToTenant(User $user, Tenants $tenants): bool
    {
        return $user->tenants()->where('tenants.id', $tenants->id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenants $tenants): bool
    {
        return $this->belongsToTenant($user, $tenants);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenants $tenants): bool
    {
        return $this->belongsToTenant($user, $tenants);
    }

    public function delete(User $user, Tenants $tenants): bool
    {
        return $this->belongsToTenant($user, $tenants);
    }
}
