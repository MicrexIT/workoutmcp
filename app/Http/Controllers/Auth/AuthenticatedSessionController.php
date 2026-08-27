<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\WorkoutMemory\OAuthLoginRedirect;
use App\Services\WorkoutMemory\WorkoutMemoryActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (User::query()->doesntExist()) {
            return redirect()->route('register');
        }

        return view('auth.login', [
            'registrationOpen' => (bool) config('workout_memory.registration.enabled'),
        ]);
    }

    public function store(LoginRequest $request, WorkoutMemoryActivityLogger $activity, OAuthLoginRedirect $oauthRedirect): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $activity->info('auth.login.succeeded', [
            ...$activity->userContext($request->user()),
            'remember' => $request->boolean('remember'),
        ], $request);

        $oauthRedirect->flash($request, redirect()->getIntendedUrl());

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request, WorkoutMemoryActivityLogger $activity): RedirectResponse
    {
        $activity->info('auth.logout.succeeded', $activity->userContext($request->user()), $request);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been signed out.');
    }
}
