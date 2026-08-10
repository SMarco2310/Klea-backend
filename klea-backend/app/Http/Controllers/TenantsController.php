<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantsRequest;
use App\Models\Tenants;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantsController extends Controller
{
    /**
     * Display a listing of the tenants the authenticated user belongs to.
     */
    public function index(Request $request)
    {
        try {
            $tenants = $request->user()->tenants()->paginate(20);

            return response()->json([
                'data' => $tenants,
                'success' => true,
                'message' => 'Fetched tenants successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tenants: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug'
        ]);

        try {
            $user = $request->user();

            $tenant = TenantUser::query()->where('user_id', $user->id)->first();
            
            $tenant = Tenants::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'status' => 'active',
            ]);

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            $user->current_tenant_id = $tenant->id;
            $user->save();

            return response()->json([
                'data' => $tenant,
                'success' => true,
                'message' => 'Workspace created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating tenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workspace',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenants $tenant)
    {
        $this->authorize('view', $tenant);

        try {
            return response()->json([
                'data' => $tenant,
                'success' => true,
                'message' => 'Fetched tenant successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantsRequest $request, Tenants $tenant)
    {
        $this->authorize('update', $tenant);

        try {
            $tenant->update($request->validated());

            return response()->json([
                'data' => $tenant,
                'success' => true,
                'message' => 'Tenant updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating tenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenants $tenant)
    {
        $this->authorize('delete', $tenant);

        try {
            // Reset current_tenant_id for users currently using this tenant
            \App\Models\User::where('current_tenant_id', $tenant->id)->update(['current_tenant_id' => null]);

            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workspace deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting tenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
