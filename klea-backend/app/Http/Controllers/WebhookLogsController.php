<?php

namespace App\Http\Controllers;

use App\Models\WebhookLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookLogsController extends Controller
{
    /**
     * Display a listing of webhook logs for the current tenant.
     */
    public function index(Request $request)
    {
        try {
            $query = WebhookLogs::whereHas('transaction.subscription.subscriber', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            });

            if ($request->filled('app_id')) {
                $query->whereHas('transaction.subscription.plan', function ($q) use ($request) {
                    $q->where('application_id', $request->app_id);
                });
            }

            $logs = $query->latest()->paginate(20);

            return response()->json([
                'data' => $logs,
                'success' => true,
                'message' => 'Fetched webhook logs successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching webhook logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch webhook logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified webhook log.
     */
    public function show(WebhookLogs $webhookLog)
    {
        $this->authorize('view', $webhookLog);

        try {
            return response()->json([
                'data' => $webhookLog,
                'success' => true,
                'message' => 'Fetched webhook log successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching webhook log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch webhook log',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
