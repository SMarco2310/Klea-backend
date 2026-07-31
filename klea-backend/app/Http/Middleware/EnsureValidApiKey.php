<?php

namespace App\Http\Middleware;

use App\Models\ApiKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidApiKey
{
    /**
     * Authenticates external app requests via "Bearer {public_id}.{secret}".
     * Resolves the calling Applications record and attaches it to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Expected header: Authorization: Bearer {public_id}.{secret}
        // public_id is safe to log/display (like a username), secret is not.
        $header = $request->bearerToken();

        if (! $header || ! str_contains($header, '.')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or malformed API key.',
            ], 401);
        }

        [$publicId, $secret] = explode('.', $header, 2);

        $apiKey = ApiKeys::where('public_id', $publicId)->whereNull('revoked_at')->first();

        // hash_equals (not ===) prevents timing attacks — a plain string compare
        // can leak how many leading characters matched via response time.
        if (! $apiKey || ! hash_equals($apiKey->secret_hash, hash('sha256', $secret))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        // Downstream controllers (Public\PlansController, Public\SubscribeController)
        // read this to know which Applications record is making the call.
        $request->attributes->set('application', $apiKey->application);

        return $next($request);
    }
}
