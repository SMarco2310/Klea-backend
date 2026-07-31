<?php

namespace App\Http\Controllers;

use App\Models\Subscriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionsController extends Controller
{
    /**
     * Display a listing of subscriptions under the current tenant.
     */
    public function index(Request $request)
    {
        try {
            // Subscriptions have no tenant_id column of their own — tenant scoping
            // goes through the subscriber they belong to.
            $subscriptions = Subscriptions::whereHas('subscriber', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            })->with(['subscriber', 'plan'])->paginate(20);

            return response()->json([
                'data' => $subscriptions,
                'success' => true,
                'message' => 'Fetched subscriptions successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscriptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscriptions $subscription)
    {
        $this->authorize('view', $subscription);

        try {
            return response()->json([
                'data' => $subscription->load(['subscriber', 'plan', 'transactions']),
                'success' => true,
                'message' => 'Fetched subscription successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the specified subscription. Subscriptions are created only
     * through the public subscribe flow, so there is no manual store().
     */
    public function destroy(Subscriptions $subscription)
    {
        $this->authorize('delete', $subscription);

        try {
            // "delete" here is a cancellation, not a row deletion — billing history
            // must stay intact for the transactions/revenue records that reference it.
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
