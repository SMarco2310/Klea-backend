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

            $clerkUser = $this->fetchClerkUser($claims['sub'] ?? '');

            $email = $clerkUser['email'] ?? null;

            if (! $email) {
                throw new \RuntimeException('Clerk user has no primary email address.');
            }

            $name = $clerkUser['name'] ?? explode('@', $email)[0];

            $user = DB::transaction(function () use ($email, $name) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        // Same-email match above means an existing password-based
                        // account is reused (merged), not duplicated.
                        'name' => $name,
                        'password' => Hash::make(Str::random(40)), // unusable random password; this account only ever logs in via Clerk
                    ]
                );

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
     * Look up a Clerk user by ID via the Backend API and return their
     * primary email + display name. Clerk's session token only carries the
     * user ID ("sub") by default — not email or name — so those have to be
     * fetched separately rather than read off the token claims.
     */
    protected function fetchClerkUser(string $clerkUserId): array
    {
        if (! $clerkUserId) {
            throw new \RuntimeException('Clerk token did not include a subject (user id) claim.');
        }

        if (! config('clerk.secret_key')) {
            throw new \RuntimeException('Clerk auth is not configured yet (missing CLERK_SECRET_KEY).');
        }

        $response = Http::withToken(config('clerk.secret_key'))
            ->get("https://api.clerk.com/v1/users/{$clerkUserId}");

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch Clerk user: ' . $response->body());
        }

        $data = $response->json();

        $primaryEmailId = $data['primary_email_address_id'] ?? null;
        $emailAddresses = $data['email_addresses'] ?? [];

        $primaryEmail = collect($emailAddresses)
            ->firstWhere('id', $primaryEmailId)['email_address']
            ?? ($emailAddresses[0]['email_address'] ?? null);

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: null;

        return [
            'email' => $primaryEmail,
            'name' => $name,
        ];
    }
}
