<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlansController extends Controller
{
    /**
     * List the calling application's active plans with their features,
     * for display in the external app before a customer subscribes.
     */
    public function index(Request $request)
    {
        try {
            // 'application' comes from EnsureValidApiKey — each API key belongs to
            // exactly one Applications record, so a caller only ever sees their own plans.
            $application = $request->attributes->get('application');

            $plans = $application->plans()
                ->active()
                ->with('features')
                ->get();

            return response()->json([
                'data' => $plans,
                'success' => true,
                'message' => 'Fetched plans successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching public plans: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
