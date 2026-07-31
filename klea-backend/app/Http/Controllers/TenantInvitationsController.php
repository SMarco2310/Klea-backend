<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantInvitationsRequest;
use App\Models\TenantInvitations;
use App\Models\TenantUser;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantInvitationsController extends Controller
{
    /**
     * Display a listing of invitations for the current tenant.
     */
    public function index(Request $request)
    {
        try {
            $invitations = TenantInvitations::where('tenant_id', $request->user()->current_tenant_id)
                ->paginate(20);

            return response()->json([
                'data' => $invitations,
                'success' => true,
                'message' => 'Fetched invitations successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching invitations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invitations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created invitation for the current tenant.
     *
     * The inviter cannot invite themselves (enforced in StoreTenantInvitationsRequest,
     * which compares the invite email against the authenticated user's own email) nor
     * someone who is already a member of the current tenant.
     */
    public function store(StoreTenantInvitationsRequest $request)
    {
        $this->authorize('create', TenantInvitations::class);

        try {
            $tenantId = $request->user()->current_tenant_id;

            $alreadyMember = TenantUser::where('tenant_id', $tenantId)
                ->whereHas('user', fn ($q) => $q->where('email', $request->email))
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the tenant.'],
                ]);
            }

            $invitation = TenantInvitations::create([
                'tenant_id' => $tenantId,
                'email' => $request->email,
                'role' => $request->role ?? 'member',
                'token' => Str::random(40),
                'invited_by' => $request->user()->id,
                'status' => 'pending',
                'expires_at' => $request->expires_at,
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new TenantInvitationNotification($invitation));

            return response()->json([
                'data' => $invitation,
                'success' => true,
                'message' => 'Invitation created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invitation',
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified invitation.
     */
    public function show(TenantInvitations $tenantInvitation)
    {
        $this->authorize('view', $tenantInvitation);

        try {
            return response()->json([
                'data' => $tenantInvitation,
                'success' => true,
                'message' => 'Fetched invitation successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke the specified invitation.
     */
    public function destroy(TenantInvitations $tenantInvitation)
    {
        $this->authorize('delete', $tenantInvitation);

        try {
            $tenantInvitation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invitation revoked successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error revoking invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept a pending invitation by token. The authenticated user must be the
     * invitee (email match) — this is also what stops someone from accepting
     * their own invitation under a different account, and confirms identity
     * since invite creation only validated the inviter, not the invitee.
     */
    public function accept(Request $request, string $token)
    {
        try {
            $invitation = TenantInvitations::where('token', $token)->pending()->firstOrFail();

            if (strcasecmp($invitation->email, $request->user()->email) !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invitation was not addressed to you.'
                ], 403);
            }

            if ($invitation->expires_at && $invitation->expires_at->isPast()) {
                $invitation->update(['status' => 'expired']);

                return response()->json([
                    'success' => false,
                    'message' => 'This invitation has expired.'
                ], 410);
            }

            DB::transaction(function () use ($invitation, $request) {
                TenantUser::firstOrCreate(
                    ['tenant_id' => $invitation->tenant_id, 'user_id' => $request->user()->id],
                    ['role' => $invitation->role]
                );

                $invitation->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                $request->user()->update(['current_tenant_id' => $invitation->tenant_id]);
            });

            return response()->json([
                'data' => $invitation->fresh(),
                'success' => true,
                'message' => 'Invitation accepted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found or already used'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error accepting invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decline a pending invitation by token. Same identity check as accept —
     * the authenticated user's email must match the invitation's.
     */
    public function decline(Request $request, string $token)
    {
        try {
            $invitation = TenantInvitations::where('token', $token)->pending()->firstOrFail();

            if (strcasecmp($invitation->email, $request->user()->email) !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invitation was not addressed to you.'
                ], 403);
            }

            $invitation->update(['status' => 'declined']);

            return response()->json([
                'data' => $invitation->fresh(),
                'success' => true,
                'message' => 'Invitation declined'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found or already used'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error declining invitation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
