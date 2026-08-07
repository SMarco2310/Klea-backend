<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenants;
use App\Models\TenantUser;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

                $this->createTenantForUser($user, $request->tenant_name);

                return $user;
            });

            event(new Registered($user));

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
     * Clerk OAuth login (Google/GitHub/etc. sign-in handled by Clerk on the
     * frontend). The frontend sends us Clerk's session token; we verify it
     * ourselves against Clerk's public keys (JWKS) rather than trusting it
     * blindly, then find-or-create a local User and issue our own Sanctum
     * token. Clerk is not involved in any request after this one.
     */
    public function loginWithClerk(Request $request)
    {
        $request->validate(['session_token' => ['required', 'string']]);

        if (! config('clerk.jwks_url') || ! config('clerk.issuer')) {
            return response()->json([
                'success' => false,
                'message' => 'Clerk auth is not configured yet (missing CLERK_JWKS_URL / CLERK_ISSUER).',
            ], 501);
        }

        try {
            $claims = $this->verifyClerkToken($request->session_token);

            $email = $claims['email'] ?? null;

            if (! $email) {
                throw new \RuntimeException('Clerk token did not include an email claim.');
            }

            $user = DB::transaction(function () use ($email, $claims) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        // Same-email match above means an existing password-based
                        // account is reused (merged), not duplicated.
                        'name' => $claims['name'] ?? explode('@', $email)[0],
                        'password' => Hash::make(Str::random(40)), // unusable random password; this account only ever logs in via Clerk
                    ]
                );

                if ($user->wasRecentlyCreated) {
                    $this->createTenantForUser($user);
                }

                return $user;
            });

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'success' => true,
                'message' => 'Logged in successfully via Clerk',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error logging in via Clerk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Clerk session',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Fetch (and cache) Clerk's JWKS, then verify the session token's
     * signature, expiry and issuer against it.
     */
    protected function verifyClerkToken(string $sessionToken): array
    {
        $jwks = Cache::remember('clerk.jwks', 3600, function () {
            $response = Http::get(config('clerk.jwks_url'));

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch Clerk JWKS: ' . $response->body());
            }

            return $response->json();
        });

        $keys = JWK::parseKeySet($jwks);
        $decoded = (array) JWT::decode($sessionToken, $keys);

        if (($decoded['iss'] ?? null) !== config('clerk.issuer')) {
            throw new \RuntimeException('Clerk token issuer mismatch.');
        }

        return $decoded;
    }

    /**
     * Create a brand-new Tenant for a user and attach them as its owner.
     * Shared by register() and first-time Clerk sign-ins so both paths give
     * a new user the exact same "one owned workspace" starting state.
     */
    protected function createTenantForUser(User $user, ?string $tenantName = null): Tenants
    {
        $tenantName = $tenantName ?: $user->name . "'s Workspace";
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

        return $tenant;
    }
}
