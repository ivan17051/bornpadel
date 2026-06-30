<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateExternalApi
{
    public function handle(Request $request, Closure $next)
    {
        $configuredKey = config('external_api.key');

        if (! $configuredKey) {
            return response()->json([
                'success' => false,
                'message' => 'External API is not configured.',
            ], 503);
        }

        $provided = $request->bearerToken() ?: $request->header('X-API-Key');

        if (! $provided || ! hash_equals($configuredKey, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
