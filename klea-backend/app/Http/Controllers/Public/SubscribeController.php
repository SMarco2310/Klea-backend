<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Models\Plans;
use App\Models\Subscribers;
use App\Models\Subscriptions;
use App\Models\Transactions;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SubscribeController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway)
    {
    }

    /**
     * A subscriber of the calling external app picks a plan. We create the
     * subscriber (if new), a pending subscription, a pending transaction,
     * then ask Semoa for a payment link and hand it back to the external app.
     */
    public function __invoke(SubscribeRequest $request)
    {
        try {
            // 'application' was resolved and attached to the request by the
            // EnsureValidApiKey middleware — this is how we know which tenant/app
            // is calling, without the caller ever sending a tenant_id directly.
            $application = $request->attributes->get('application');
            $tenant = $application->tenant;

            // Plan must belong to THIS application — stops an app from subscribing
            // a customer to a plan that belongs to a different tenant's app.
            $plan = Plans::where('id', $request->plan_id)
                ->where('application_id', $application->id)
                ->firstOrFail();

            // subscriber + subscription + transaction are created together so we
            // never end up with a subscription that has no billing record, or vice versa.
            [$subscription, $transaction] = DB::transaction(function () use ($request, $tenant, $plan) {
                // external_id is the customer's id in the EXTERNAL app's own system —
                // firstOrCreate means repeat subscribes from the same customer reuse
                // the same Subscribers row instead of duplicating it.
                $subscriber = Subscribers::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'external_id' => $request->external_id],
                    [
                        'phone_number' => $request->phone_number,
                        'email' => $request->email,
                        'environment' => $request->environment ?? 'live',
                    ]
                );

                // Both start as "pending" — they only flip to active/successful once
                // Semoa's callback confirms the payment actually went through.
                $subscription = Subscriptions::create([
                    'subscriber_id' => $subscriber->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($plan->duration_days),
                    'environment' => $request->environment ?? 'live',
                ]);

                $transaction = Transactions::create([
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'phone_number' => $request->phone_number,
                    'status' => 'pending',
                    'environment' => $request->environment ?? 'live',
                ]);

                return [$subscription, $transaction];
            });

            // This is the URL Semoa will POST to once the customer pays (or fails/cancels).
            $callbackUrl = URL::to("/api/public/semoa/callback/{$tenant->id}");

            $order = $this->gateway->createOrder($tenant, $transaction, $callbackUrl);

            // Save Semoa's own reference for this bill so we can look it up later
            // (e.g. manual status checks) independent of the callback.
            $transaction->update(['provider_tx_id' => $order['order_reference'] ?? $order['code'] ?? null]);

            return response()->json([
                'data' => [
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'payment_url' => $order['bill_url'] ?? null,
                    'qrcode_url' => $order['qrcode_url'] ?? null,
                ],
                'success' => true,
                'message' => 'Subscription created, complete payment via payment_url'
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found for this application'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
