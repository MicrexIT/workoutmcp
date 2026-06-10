<?php

use App\Http\Controllers\OAuth\ChatGptOAuthController;
use App\Http\Middleware\EnsureMcpOAuthToken;
use App\Mcp\Servers\WorkoutMemoryServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-protected-resource', [ChatGptOAuthController::class, 'protectedResource'])
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [ChatGptOAuthController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', [ChatGptOAuthController::class, 'authorizationServer'])
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [ChatGptOAuthController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server.nested');
Route::get('/.well-known/openid-configuration', [ChatGptOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.openid-configuration');
Route::get('/.well-known/openid-configuration/{path}', [ChatGptOAuthController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('workout-memory.oauth.openid-configuration.nested');
Route::get('/mcp/workout-memory/.well-known/oauth-authorization-server', [ChatGptOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.authorization-server.resource');
Route::get('/mcp/workout-memory/.well-known/openid-configuration', [ChatGptOAuthController::class, 'authorizationServer'])
    ->name('workout-memory.oauth.openid-configuration.resource');
Route::post('/oauth/register', [ChatGptOAuthController::class, 'register'])
    ->middleware('throttle:60,1')
    ->name('workout-memory.oauth.register');
Route::get('/oauth/authorize', [ChatGptOAuthController::class, 'authorize'])
    ->middleware(['web', 'auth', 'throttle:60,1'])
    ->name('workout-memory.oauth.authorize');
Route::post('/oauth/token', [ChatGptOAuthController::class, 'token'])
    ->middleware('throttle:60,1')
    ->name('workout-memory.oauth.token');

Mcp::web('/mcp/workout-memory', WorkoutMemoryServer::class)
    ->middleware(EnsureMcpOAuthToken::class);
