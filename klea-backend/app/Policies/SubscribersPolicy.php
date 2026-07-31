<?php

namespace App\Policies;

use App\Models\Subscribers;
use App\Models\User;

class SubscribersPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscribers $subscribers): bool
    {
        return $subscribers->tenant_id === $user->current_tenant_id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Subscribers $subscribers): bool
    {
        return $subscribers->tenant_id === $user->current_tenant_id;
    }

    public function delete(User $user, Subscribers $subscribers): bool
    {
        return $subscribers->tenant_id === $user->current_tenant_id;
    }
}
