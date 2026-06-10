<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpPrivateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('workout_memory.mcp_private_token');

        if (! is_string($expectedToken) || $expectedToken === '') {
            return response()->json([
                'message' => 'MCP_PRIVATE_TOKEN is not configured.',
            ], 503);
        }

        $providedToken = $request->bearerToken();

        if (! hash_equals($expectedToken, $providedToken ?? '')) {
            return response()->json([
                'message' => 'Invalid MCP bearer token.',
            ], 401);
        }

        return $next($request);
    }
}
