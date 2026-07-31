<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachFeatureToPlanRequest;
use App\Http\Requests\StorePlansRequest;
use App\Http\Requests\UpdatePlansRequest;
use App\Models\Applications;
use App\Models\Features;
use App\Models\Plans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PlansController extends Controller
{
    /**
     * Display a listing of plans under the current tenant's applications.
     */
    public function index(Request $request)
    {
        try {
            $plans = Plans::whereHas('application', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            })->paginate(20);

            return response()->json([
                'data' => $plans,
                'success' => true,
                'message' => 'Fetched plans successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching plans: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created plan under an application belonging to the current tenant.
     */
    public function store(StorePlansRequest $request)
    {
        $this->authorize('create', Plans::class);

        try {
            $application = Applications::where('id', $request->application_id)
                ->where('tenant_id', $request->user()->current_tenant_id)
                ->firstOrFail();

            $plan = Plans::create($request->validated());

            return response()->json([
                'data' => $plan,
                'success' => true,
                'message' => 'Plan created successfully'
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found in the current tenant'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified plan.
     */
    public function show(Plans $plan)
    {
        $this->authorize('view', $plan);

        try {
            return response()->json([
                'data' => $plan->load('features'),
                'success' => true,
                'message' => 'Fetched plan successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified plan.
     */
    public function update(UpdatePlansRequest $request, Plans $plan)
    {
        $this->authorize('update', $plan);

        try {
            if ($request->filled('application_id')) {
                Applications::where('id', $request->application_id)
                    ->where('tenant_id', $request->user()->current_tenant_id)
                    ->firstOrFail();
            }

            $plan->update($request->validated());

            return response()->json([
                'data' => $plan,
                'success' => true,
                'message' => 'Plan updated successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found in the current tenant'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft-delete the specified plan, unless it has active subscriptions.
     */
    public function destroy(Plans $plan)
    {
        $this->authorize('delete', $plan);

        try {
            if ($plan->subscriptions()->active()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a plan with active subscriptions'
                ], 409);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Attach a feature to the plan, optionally with a usage limit.
     * The feature must belong to the same application as the plan.
     */
    public function attachFeature(AttachFeatureToPlanRequest $request, Plans $plan)
    {
        $this->authorize('update', $plan);

        try {
            $feature = Features::where('id', $request->feature_id)
                ->where('application_id', $plan->application_id)
                ->first();

            if (! $feature) {
                throw ValidationException::withMessages([
                    'feature_id' => ['This feature does not belong to the same application as the plan.'],
                ]);
            }

            $plan->features()->syncWithoutDetaching([
                $feature->id => ['limit' => $request->limit],
            ]);

            return response()->json([
                'data' => $plan->load('features'),
                'success' => true,
                'message' => 'Feature attached to plan successfully'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid feature',
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error attaching feature to plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to attach feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detach a feature from the plan.
     */
    public function detachFeature(Plans $plan, Features $feature)
    {
        $this->authorize('update', $plan);

        try {
            $plan->features()->detach($feature->id);

            return response()->json([
                'success' => true,
                'message' => 'Feature detached from plan successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error detaching feature from plan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
