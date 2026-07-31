<?php

namespace App\Policies;

use App\Models\ApiKeys;
use App\Models\User;

class ApiKeysPolicy
{
    protected function belongsToCurrentTenant(User $user, ApiKeys $apiKeys): bool
    {
        return $apiKeys->application->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ApiKeys $apiKeys): bool
    {
        return $this->belongsToCurrentTenant($user, $apiKeys);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, ApiKeys $apiKeys): bool
    {
        return $this->belongsToCurrentTenant($user, $apiKeys);
    }

    public function delete(User $user, ApiKeys $apiKeys): bool
    {
        return $this->belongsToCurrentTenant($user, $apiKeys);
    }
}
