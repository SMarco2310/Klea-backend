<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeaturesRequest;
use App\Http\Requests\UpdateFeaturesRequest;
use App\Models\Applications;
use App\Models\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeaturesController extends Controller
{
    /**
     * Display a listing of features under the current tenant's applications.
     */
    public function index(Request $request)
    {
        try {
            $features = Features::whereHas('application', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            })->paginate(20);

            return response()->json([
                'data' => $features,
                'success' => true,
                'message' => 'Fetched features successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching features: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch features',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created feature under an application belonging to the current tenant.
     */
    public function store(StoreFeaturesRequest $request)
    {
        $this->authorize('create', Features::class);

        try {
            Applications::where('id', $request->application_id)
                ->where('tenant_id', $request->user()->current_tenant_id)
                ->firstOrFail();

            $feature = Features::create($request->validated());

            return response()->json([
                'data' => $feature,
                'success' => true,
                'message' => 'Feature created successfully'
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found in the current tenant'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating feature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified feature.
     */
    public function show(Features $feature)
    {
        $this->authorize('view', $feature);

        try {
            return response()->json([
                'data' => $feature,
                'success' => true,
                'message' => 'Fetched feature successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching feature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified feature.
     */
    public function update(UpdateFeaturesRequest $request, Features $feature)
    {
        $this->authorize('update', $feature);

        try {
            if ($request->filled('application_id')) {
                Applications::where('id', $request->application_id)
                    ->where('tenant_id', $request->user()->current_tenant_id)
                    ->firstOrFail();
            }

            $feature->update($request->validated());

            return response()->json([
                'data' => $feature,
                'success' => true,
                'message' => 'Feature updated successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found in the current tenant'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating feature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified feature.
     */
    public function destroy(Features $feature)
    {
        $this->authorize('delete', $feature);

        try {
            $feature->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feature deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting feature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
