<?php

namespace App\Policies;

use App\Models\Subscriptions;
use App\Models\User;

class SubscriptionsPolicy
{
    protected function belongsToCurrentTenant(User $user, Subscriptions $subscriptions): bool
    {
        return $subscriptions->subscriber->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscriptions $subscriptions): bool
    {
        return $this->belongsToCurrentTenant($user, $subscriptions);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Subscriptions $subscriptions): bool
    {
        return $this->belongsToCurrentTenant($user, $subscriptions);
    }

    public function delete(User $user, Subscriptions $subscriptions): bool
    {
        return $this->belongsToCurrentTenant($user, $subscriptions);
    }
}
