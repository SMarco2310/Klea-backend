<?php

namespace App\Services\Payments;

use App\Models\Tenants;
use App\Models\Transactions;

// Contract for any payment provider. Code that needs to charge a customer
// depends on this interface, not on SemoaPaymentGateway directly — swapping
// providers later only means writing a new class and rebinding it in
// AppServiceProvider, no controller changes needed.
interface PaymentGatewayInterface
{
    /**
     * Create a bill for the given transaction and return the gateway's response
     * (at minimum: a payment link the subscriber can be redirected to).
     */
    public function createOrder(Tenants $tenant, Transactions $transaction, string $callbackUrl): array;

    /**
     * Check the current status of a previously created order by its gateway code.
     */
    public function getOrderStatus(Tenants $tenant, string $code): array;

    /**
     * Decode and verify an inbound callback payload for the given tenant,
     * returning the normalized payment result.
     */
    public function decodeCallback(Tenants $tenant, string $rawPayload): array;
}
