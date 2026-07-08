<?php

namespace App\Http\Middleware;

use App\Services\WorkoutMemory\McpOAuthServer;
use App\Services\WorkoutMemory\WorkoutMemoryActivityLogger;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpOAuthToken
{
    public function __construct(private readonly WorkoutMemoryActivityLogger $activity) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $oauth = app(McpOAuthServer::class);
        $user = $oauth->userForAccessToken($request->bearerToken(), $request->path());

        if ($user === null) {
            $this->activity->warning('mcp.oauth_token.rejected', [
                'has_bearer_token' => is_string($request->bearerToken()) && $request->bearerToken() !== '',
                'resource_path' => $request->path(),
            ], $request);

            return response()->json([
                'message' => 'Invalid or missing MCP OAuth bearer token.',
            ], 401)->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.$oauth->protectedResourceMetadataUrl($request->path()).'", scope="'.McpOAuthServer::Scope.'"',
            );
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $this->activity->warning('mcp.oauth_token.unverified_user_rejected', [
                ...$this->activity->userContext($user),
                'resource_path' => $request->path(),
            ], $request);

            return response()->json([
                'message' => 'Your email address is not verified.',
            ], 403);
        }

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
