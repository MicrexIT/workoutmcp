<?php

namespace App\Http\Middleware;

use App\Services\WorkoutMemory\ChatGptOAuthServer;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpOAuthToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $oauth = app(ChatGptOAuthServer::class);
        $user = $oauth->userForAccessToken($request->bearerToken(), $request->path());

        if ($user === null) {
            return response()->json([
                'message' => 'Invalid or missing MCP OAuth bearer token.',
            ], 401)->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.$oauth->protectedResourceMetadataUrl($request->path()).'", scope="'.ChatGptOAuthServer::Scope.'"',
            );
        }

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
