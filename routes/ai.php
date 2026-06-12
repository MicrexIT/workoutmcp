<?php

use App\Http\Controllers\OAuth\McpOAuthController;
use App\Http\Middleware\EnsureMcpOAuthToken;
use App\Mcp\Servers\WorkoutMemoryServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource'])
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [McpOAuthController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [McpOAuthController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server.nested');
Route::get('/.well-known/openid-configuration', [McpOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.openid-configuration');
Route::get('/.well-known/openid-configuration/{path}', [McpOAuthController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('workout-memory.oauth.openid-configuration.nested');
Route::get('/mcp/workout-memory/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.authorization-server.resource');
Route::get('/mcp/workout-memory/.well-known/openid-configuration', [McpOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.openid-configuration.resource');
Route::post('/oauth/register', [McpOAuthController::class, 'register'])
    ->middleware('throttle:60,1')
    ->name('workout-memory.oauth.register');
Route::get('/oauth/authorize', [McpOAuthController::class, 'authorize'])
    ->middleware(['web', 'auth', 'throttle:60,1'])
    ->name('workout-memory.oauth.authorize');
Route::post('/oauth/authorize', [McpOAuthController::class, 'decide'])
    ->middleware(['web', 'auth', 'throttle:60,1'])
    ->name('workout-memory.oauth.authorize.decision');
Route::post('/oauth/token', [McpOAuthController::class, 'token'])
    ->middleware('throttle:60,1')
    ->name('workout-memory.oauth.token');

Mcp::web('/mcp/workout-memory', WorkoutMemoryServer::class)
    ->middleware(EnsureMcpOAuthToken::class);
