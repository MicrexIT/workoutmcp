<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! $this->registrationOpen()) {
            return redirect()->route('login')
                ->with('status', 'Registration is closed.');
        }

        return view('auth.register');
    }

    public function store(RegisterUserRequest $request, CurrentUserResolver $users): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

        $users->withProfile($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))
            ->with('status', 'Account created.');
    }

    private function registrationOpen(): bool
    {
        return User::query()->doesntExist() || (bool) config('workout_memory.registration.enabled');
    }
}
