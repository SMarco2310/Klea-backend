<?php

namespace App\Http\Controllers;

use App\Models\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionsController extends Controller
{
    /**
     * Display a listing of transactions under the current tenant.
     */
    public function index(Request $request)
    {
        try {
            $query = Transactions::whereHas('subscription.subscriber', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            });

            if ($request->filled('environment')) {
                $query->where('environment', $request->environment);
            }

            $transactions = $query->latest()->paginate(20);

            return response()->json([
                'data' => $transactions,
                'success' => true,
                'message' => 'Fetched transactions successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching transactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transactions $transaction)
    {
        $this->authorize('view', $transaction);

        try {
            return response()->json([
                'data' => $transaction->load('webhookLogs'),
                'success' => true,
                'message' => 'Fetched transaction successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Total successful revenue for the current tenant, optionally scoped to
     * a date range via ?from=YYYY-MM-DD&to=YYYY-MM-DD.
     *
     * This is computed on the fly from Transactions rather than stored anywhere —
     * there is no running balance/ledger table, revenue is always "sum what happened".
     */
    public function summary(Request $request)
    {
        try {
            $query = Transactions::whereHas('subscription.subscriber', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->current_tenant_id);
            })->where('status', 'successful');

            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->to);
            }

            if ($request->filled('environment')) {
                $query->where('environment', $request->environment);
            }

            return response()->json([
                'data' => [
                    'total_amount' => (float) $query->sum('amount'),
                    'transaction_count' => $query->count(),
                ],
                'success' => true,
                'message' => 'Fetched revenue summary successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching transaction summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch revenue summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
