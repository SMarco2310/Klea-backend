<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookLogs;

class WebhookLogsPolicy
{
    protected function belongsToCurrentTenant(User $user, WebhookLogs $webhookLogs): bool
    {
        return $webhookLogs->transaction->subscription->subscriber->tenant_id === $user->current_tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebhookLogs $webhookLogs): bool
    {
        return $this->belongsToCurrentTenant($user, $webhookLogs);
    }

    public function create(User $user): bool
    {
        return ! is_null($user->current_tenant_id);
    }

    public function update(User $user, WebhookLogs $webhookLogs): bool
    {
        return $this->belongsToCurrentTenant($user, $webhookLogs);
    }

    public function delete(User $user, WebhookLogs $webhookLogs): bool
    {
        return $this->belongsToCurrentTenant($user, $webhookLogs);
    }
}
