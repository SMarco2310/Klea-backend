<?php

namespace App\Policies;

use App\Models\TenantInvitations;
use App\Models\TenantUser;
use App\Models\User;

class TenantInvitationsPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TenantInvitations $tenantInvitations): bool
    {
        return $tenantInvitations->tenant_id === $user->current_tenant_id;
    }

    /**
     * Only owners/admins of the current tenant may send invitations.
     */
    public function create(User $user): bool
    {
        if (is_null($user->current_tenant_id)) {
            return false;
        }

        return TenantUser::where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function update(User $user, TenantInvitations $tenantInvitations): bool
    {
        return $this->create($user) && $tenantInvitations->tenant_id === $user->current_tenant_id;
    }

    /**
     * Revoking an invitation requires the same owner/admin permission as creating one.
     */
    public function delete(User $user, TenantInvitations $tenantInvitations): bool
    {
        return $this->create($user) && $tenantInvitations->tenant_id === $user->current_tenant_id;
    }
}
