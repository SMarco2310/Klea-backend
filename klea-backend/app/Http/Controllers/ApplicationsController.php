<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationsRequest;
use App\Http\Requests\UpdateApplicationsRequest;
use App\Models\Applications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationsController extends Controller
{
    /**
     * Display a listing of the current tenant's applications.
     */
    public function index(Request $request)
    {
        try {
            $applications = Applications::where('tenant_id', $request->user()->current_tenant_id)
                ->paginate(20);

            return response()->json([
                'data' => $applications,
                'success' => true,
                'message' => 'Fetched applications successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching applications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created application under the current tenant.
     */
    public function store(StoreApplicationsRequest $request)
    {
        $this->authorize('create', Applications::class);

        try {
            $application = Applications::create([
                ...$request->validated(),
                'tenant_id' => $request->user()->current_tenant_id,
            ]);

            return response()->json([
                'data' => $application,
                'success' => true,
                'message' => 'Application created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating application: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified application.
     */
    public function show(Applications $application)
    {
        $this->authorize('view', $application);

        try {
            return response()->json([
                'data' => $application,
                'success' => true,
                'message' => 'Fetched application successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching application: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified application.
     */
    public function update(UpdateApplicationsRequest $request, Applications $application)
    {
        $this->authorize('update', $application);

        try {
            $application->update($request->validated());

            return response()->json([
                'data' => $application,
                'success' => true,
                'message' => 'Application updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating application: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified application.
     */
    public function destroy(Applications $application)
    {
        $this->authorize('delete', $application);

        try {
            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Application deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting application: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete application',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
