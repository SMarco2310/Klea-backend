<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSubscribersRequest;
use App\Models\Subscribers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscribersController extends Controller
{
    /**
     * Display a listing of subscribers under the current tenant.
     */
    public function index(Request $request)
    {
        try {
            $subscribers = Subscribers::where('tenant_id', $request->user()->current_tenant_id)
                ->paginate(20);

            return response()->json([
                'data' => $subscribers,
                'success' => true,
                'message' => 'Fetched subscribers successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscribers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscribers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified subscriber.
     */
    public function show(Subscribers $subscriber)
    {
        $this->authorize('view', $subscriber);

        try {
            return response()->json([
                'data' => $subscriber->load('subscriptions'),
                'success' => true,
                'message' => 'Fetched subscriber successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscriber: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriber',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified subscriber's contact details.
     * No store()/destroy() here — subscribers are only ever created by the
     * public subscribe flow (see Public\SubscribeController), and deleting one
     * would orphan their billing history.
     */
    public function update(UpdateSubscribersRequest $request, Subscribers $subscriber)
    {
        $this->authorize('update', $subscriber);

        try {
            $subscriber->update($request->safe()->only(['phone_number', 'email']));

            return response()->json([
                'data' => $subscriber,
                'success' => true,
                'message' => 'Subscriber updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating subscriber: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscriber',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
