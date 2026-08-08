<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchTenantRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display the authenticated user's profile.
    **/

    public function show(Request $request)
    {
        try {
            return response()->json([
                'data' => $request->user()->load('tenants'),
                'success' => true,
                'message' => 'Fetched profile successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the authenticated user's own profile.
    **/

    public function update(UpdateUserRequest $request)
    {
        try {
            $user = $request->user();

            $data = $request->only(['name', 'email']);

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $changingEmail = $request->filled('email') && $request->email !== $user->email;
            if ($changingEmail) {
                $data['email_verified_at'] = null;
            }

            $user->update($data);

            if ($changingEmail) {
                $user->sendEmailVerificationNotification();
            }

            return response()->json([
                'data' => $user,
                'success' => true,
                'message' => 'Profile updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Switch the authenticated user's active tenant.
     * Only tenants the user actually belongs to (via tenant_user) are allowed.
    **/

    public function switchTenant(SwitchTenantRequest $request)
    {
        try {
            $user = $request->user();

            $belongsToTenant = $user->tenants()->where('tenants.id', $request->tenant_id)->exists();

            if (! $belongsToTenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not belong to this tenant.'
                ], 403);
            }

            $user->update(['current_tenant_id' => $request->tenant_id]);

            return response()->json([
                'data' => $user->load('currentTenant'),
                'success' => true,
                'message' => 'Switched tenant successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error switching tenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
