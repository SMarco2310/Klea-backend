<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenants;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and their own tenant, then issue a Sanctum token.
     */
    public function register(RegisterRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                $tenantName = $request->tenant_name ?: $request->name . "'s Workspace";
                $slug = Str::slug($tenantName) . '-' . Str::random(6);

                $tenant = Tenants::create([
                    'name' => $tenantName,
                    'slug' => $slug,
                    'status' => 'active',
                ]);

                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                ]);

                $user->current_tenant_id = $tenant->id;
                $user->save();

                return $user;
            });

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'success' => true,
                'message' => 'Registered successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error registering user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Authenticate a user by email/password and issue a Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'success' => true,
                'message' => 'Logged in successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error logging in: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to log in',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error logging out: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to log out',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stub for future Clerk OAuth integration (Google/GitHub sign-in).
     *
     * Intended flow once Clerk is configured:
     * 1. Frontend authenticates via Clerk (incl. Google/GitHub), obtains a Clerk session token.
     * 2. Frontend sends that token here.
     * 3. Backend verifies it against Clerk's JWKS (CLERK_JWKS_URL, CLERK_ISSUER env vars).
     * 4. Backend finds-or-creates a local User by the verified email, running the same
     *    tenant-creation flow as register() for brand-new users.
     * 5. Backend issues its own Sanctum token — Clerk is never involved past this point.
     */
    
    public function loginWithClerk(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Clerk auth not yet configured. Expected: verify Clerk session JWT against Clerk JWKS, find-or-create local User by email, issue Sanctum token.',
        ], 501);
    }
}
