<?php

namespace App\Services\Payments;

use App\Models\Tenants;
use App\Models\Transactions;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SemoaPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('semoa.base_url'), '/');
    }

    /**
     * Fetch (or reuse a cached) OAuth2 access token. One shared Klea-level
     * Semoa account is used for every tenant's order creation calls.
     */
    protected function getAccessToken(): string
    {
        // Cache::remember avoids re-authenticating on every request; the token
        // is shared across all tenants since it's a single Klea-level login.
        return Cache::remember('semoa.access_token', 240, function () {
            $response = Http::post("{$this->baseUrl}/auth", [
                'client_id' => config('semoa.client_id'),
                'client_secret' => config('semoa.client_secret'),
                'username' => config('semoa.username'),
                'password' => config('semoa.password'),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Semoa authentication failed: ' . $response->body());
            }

            $data = $response->json();

            // Store for slightly less than the real TTL (-30s buffer) so we never
            // hand out a token that expires mid-request.
            Cache::put('semoa.access_token', $data['access_token'], max(($data['expires_in'] ?? 300) - 30, 30));

            return $data['access_token'];
        });
    }

    /**
     * Build the per-tenant merchant identification headers required alongside
     * the shared Bearer token. login = tenant's semoa_merchant_id,
     * apisecure = sha256(login + apikey + salt).
     */
    protected function merchantHeaders(Tenants $tenant): array
    {
        $salt = random_int(100000, PHP_INT_MAX);
        $login = $tenant->semoa_merchant_id;
        $apiKey = $tenant->semoa_api_key;

        return [
            'login' => $login,
            'apireference' => $salt,
            'salt' => $salt,
            'apisecure' => hash('sha256', $login . $apiKey . $salt),
        ];
    }

    public function createOrder(Tenants $tenant, Transactions $transaction, string $callbackUrl): array
    {
        $subscriber = $transaction->subscription->subscriber;

        // "merchant_reference" is how we recognize this order again later when
        // Semoa calls our callback URL — it's our own Transactions.id round-tripped.
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders($this->merchantHeaders($tenant))
            ->post("{$this->baseUrl}/orders", [
                'amount' => (int) $transaction->amount,
                'currency' => $transaction->currency,
                'merchant_reference' => (string) $transaction->id,
                'description' => 'Subscription payment',
                'client' => [
                    'phone' => $subscriber->phone_number,
                ],
                'callback_url' => $callbackUrl,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Semoa order creation failed: ' . $response->body());
        }

        // Response includes bill_url — the hosted payment page we redirect the subscriber to.
        return $response->json();
    }

    public function getOrderStatus(Tenants $tenant, string $code): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders($this->merchantHeaders($tenant))
            ->get("{$this->baseUrl}/orders/{$code}/status");

        if (! $response->successful()) {
            throw new RuntimeException('Semoa status check failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Semoa's callback body is a JWT signed with the tenant's own apikey
     * (semoa_api_key) as the HS256 secret.
     */
    public function decodeCallback(Tenants $tenant, string $rawPayload): array
    {
        $body = json_decode($rawPayload, true);
        $token = $body['token'] ?? null;

        if (! $token) {
            throw new RuntimeException('Missing token in Semoa callback payload');
        }

        // JWT::decode both parses AND verifies the signature in one call — if the
        // signature doesn't match (wrong apikey, tampered payload), it throws.
        // This is what proves the callback really came from Semoa for this tenant.
        $decoded = JWT::decode($token, new Key($tenant->semoa_api_key, 'HS256'));

        return (array) $decoded;
    }
}
