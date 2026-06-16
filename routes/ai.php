<?php

use App\Http\Controllers\OAuth\McpOAuthController;
use App\Http\Middleware\EnsureMcpOAuthToken;
use App\Mcp\Servers\WorkoutMemoryServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource'])
    ->middleware('throttle:oauth-metadata')
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [McpOAuthController::class, 'protectedResource'])
    ->middleware('throttle:oauth-metadata')
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server.nested');
Route::get('/.well-known/openid-configuration', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->name('workout-memory.oauth.openid-configuration');
Route::get('/.well-known/openid-configuration/{path}', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->where('path', '.*')
    ->name('workout-memory.oauth.openid-configuration.nested');
Route::get('/mcp/workout-memory/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->name('workout-memory.oauth.authorization-server.resource');
Route::get('/mcp/workout-memory/.well-known/openid-configuration', [McpOAuthController::class, 'authorizationServer'])
    ->middleware('throttle:oauth-metadata')
    ->name('workout-memory.oauth.openid-configuration.resource');
Route::post('/oauth/register', [McpOAuthController::class, 'register'])
    ->middleware('throttle:oauth-register')
    ->name('workout-memory.oauth.register');
Route::get('/oauth/authorize', [McpOAuthController::class, 'authorize'])
    ->middleware(['web', 'auth', 'verified', 'throttle:oauth-authorize'])
    ->name('workout-memory.oauth.authorize');
Route::post('/oauth/authorize', [McpOAuthController::class, 'decide'])
    ->middleware(['web', 'auth', 'verified', 'throttle:oauth-authorize'])
    ->name('workout-memory.oauth.authorize.decision');
Route::post('/oauth/token', [McpOAuthController::class, 'token'])
    ->middleware('throttle:oauth-token')
    ->name('workout-memory.oauth.token');

Mcp::web('/mcp/workout-memory', WorkoutMemoryServer::class)
    ->middleware(['throttle:mcp-unauthenticated', EnsureMcpOAuthToken::class, 'throttle:mcp']);
