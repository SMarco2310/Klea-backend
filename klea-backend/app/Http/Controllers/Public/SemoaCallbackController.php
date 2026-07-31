<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transactions;
use App\Models\WebhookLogs;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemoaCallbackController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway)
    {
    }

    /**
     * Semoa POSTs a signed JWT here when a bill's state changes (Paid, Error,
     * Cancelled, ...). We decode it with the tenant's own semoa_api_key,
     * update the matching Transaction/Subscription, log the raw callback,
     * then relay the outcome to the tenant's own application webhook_url.
     */
    public function __invoke(Request $request, Tenants $tenant)
    {
        $statusCode = 200;

        try {
            // Verifies the JWT signature (proves it's really from Semoa for this
            // tenant) and returns the decoded payment result.
            $result = $this->gateway->decodeCallback($tenant, $request->getContent());

            // merchant_reference is the Transactions.id we sent when creating the order
            // (see SubscribeController) — this is how we match the callback back to our record.
            $transaction = Transactions::where('id', $result['merchant_reference'] ?? null)->first();

            if (! $transaction) {
                $statusCode = 404;
                throw new \RuntimeException('No matching transaction for merchant_reference ' . ($result['merchant_reference'] ?? 'null'));
            }

            $state = $result['state'] ?? null;
            $transaction->update([
                'status' => $this->mapState($state),
                'provider_tx_id' => $result['order_reference'] ?? $transaction->provider_tx_id,
            ]);

            $subscription = $transaction->subscription;

            // Only a fully "Paid" state activates the subscription. Partial/Excess/Pending
            // states leave the subscription pending until a later callback resolves it.
            if ($state === 'Paid') {
                $subscription->update(['status' => 'active']);
            } elseif (in_array($state, ['Error', 'Canceled'])) {
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            // Every callback attempt is logged, success or failure — this is the audit
            // trail an admin/dev would check if a payment "went missing".
            WebhookLogs::create([
                'provider_tx_id' => $transaction->id,
                'payload' => $result,
                'status_code' => (string) $statusCode,
                'processed_at' => now(),
            ]);

            // This is the actual point of the whole flow: tell the tenant's app the
            // payment resolved, so they can unlock features for their own customer.
            $this->notifyExternalApp($subscription, $transaction, $result);

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            $statusCode = $statusCode === 200 ? 500 : $statusCode;

            Log::error('Error processing Semoa callback: ' . $e->getMessage());

            // Log failures too (bad signature, unknown transaction, etc.) — silent
            // failures here would mean a real payment never reaches the tenant's app.
            WebhookLogs::create([
                'provider_tx_id' => $transaction->id ?? null,
                'payload' => ['error' => $e->getMessage(), 'raw' => $request->getContent()],
                'status_code' => (string) $statusCode,
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $statusCode);
        }
    }

    // Maps Semoa's own state vocabulary onto our internal Transactions.status values.
    protected function mapState(?string $state): string
    {
        return match ($state) {
            'Paid' => 'successful',
            'Error', 'Canceled' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Push the payment result to the tenant application's own webhook_url so
     * they can unlock the paid features on their side.
     */
    protected function notifyExternalApp($subscription, Transactions $transaction, array $result): void
    {
        $application = $subscription->plan->application;

        // Tenant never configured a webhook_url — nothing to notify, this is not an error.
        if (! $application->webhook_url) {
            return;
        }

        // Signs the payload with the app's own webhook_secret so they can verify
        // this request genuinely came from Klea and wasn't spoofed.
        Http::withHeaders(['X-Klea-Signature' => hash_hmac('sha256', $transaction->id, $application->webhook_secret ?? '')])
            ->post($application->webhook_url, [
                'event' => 'subscription.payment_result',
                'status' => $transaction->status,
                'subscription_id' => $subscription->id,
                'subscriber_external_id' => $subscription->subscriber->external_id,
                'plan_id' => $subscription->plan_id,
                'features' => $subscription->plan->features()->get(['features.id', 'code', 'feature_plan.limit'])->toArray(),
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'received_amount' => $result['received_amount'] ?? null,
                    'payments' => $result['payments'] ?? [],
                ],
            ]);
    }
}
