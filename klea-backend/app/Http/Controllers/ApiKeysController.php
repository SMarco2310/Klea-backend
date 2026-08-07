<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiKeysRequest;
use App\Models\Applications;
use App\Models\ApiKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiKeysController extends Controller
{
    /**
     * Display a listing of API keys under the current tenant's applications.
     */
    public function index(Request $request)
    {
        try {
            $apiKeys = ApiKeys::whereHas('application', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            })->paginate(20);

            return response()->json([
                'data' => $apiKeys,
                'success' => true,
                'message' => 'Fetched API keys successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching API keys: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch API keys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created API key under an application belonging to the current tenant.
     * The plaintext secret is only ever returned once, on creation.
     */
    public function store(StoreApiKeysRequest $request)
    {
        $this->authorize('create', ApiKeys::class);

        try {
            // Confirms the application_id actually belongs to the tenant making
            // the request — stops a tenant from generating keys for someone else's app.
            Applications::where('id', $request->application_id)
                ->where('tenant_id', $request->user()->current_tenant_id)
                ->firstOrFail();

            // Only the SHA-256 hash is ever stored — like a password, the real
            // secret can't be recovered from the database if it leaks.
            $plainSecret = Str::random(40);

            $apiKey = ApiKeys::create([
                'application_id' => $request->application_id,
                'name' => $request->name,
                'environment' => $request->environment,
                'public_id' => Str::random(16),
                'secret_hash' => hash('sha256', $plainSecret),
            ]);

            // This is the one and only time the plaintext secret is returned —
            // it is never stored anywhere and can't be shown again after this response.
            return response()->json([
                'data' => [
                    ...$apiKey->toArray(),
                    'secret' => $plainSecret,
                ],
                'success' => true,
                'message' => 'API key created successfully. Store the secret now, it will not be shown again.'
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found in the current tenant'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating API key: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified API key (never exposes the secret).
     */
    public function show(ApiKeys $apiKey)
    {
        $this->authorize('view', $apiKey);

        try {
            return response()->json([
                'data' => $apiKey,
                'success' => true,
                'message' => 'Fetched API key successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching API key: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke the specified API key. This is a soft revoke (sets revoked_at),
     * not a row deletion — EnsureValidApiKey rejects any key with revoked_at set,
     * but the row stays for audit trail (who had access, when it was cut off).
     */
    public function destroy(ApiKeys $apiKey)
    {
        $this->authorize('delete', $apiKey);

        try {
            $apiKey->update(['revoked_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'API key revoked successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error revoking API key: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
