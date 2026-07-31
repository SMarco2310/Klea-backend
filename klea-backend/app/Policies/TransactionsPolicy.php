<?php

namespace App\Policies;

use App\Models\Transactions;
use App\Models\User;

class TransactionsPolicy
{
    protected function belongsToCurrentTenant(User $user, Transactions $transactions): bool
    {
        return $transactions->subscription->subscriber->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transactions $transactions): bool
    {
        return $this->belongsToCurrentTenant($user, $transactions);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, Transactions $transactions): bool
    {
        return $this->belongsToCurrentTenant($user, $transactions);
    }

    public function delete(User $user, Transactions $transactions): bool
    {
        return $this->belongsToCurrentTenant($user, $transactions);
    }
}
