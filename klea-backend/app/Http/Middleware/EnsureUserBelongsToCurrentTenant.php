<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToCurrentTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $belongsToCurrentTenant = ! is_null($user->current_tenant_id)
            && $user->tenants()->where('tenants.id', $user->current_tenant_id)->exists();

        if (! $belongsToCurrentTenant) {
            $fallbackTenantId = $user->tenants()->value('tenants.id');

            $user->current_tenant_id = $fallbackTenantId;
            $user->save();

            if (is_null($fallbackTenantId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active tenant selected.',
                ], 409);
            }
        }

        return $next($request);
    }
}
