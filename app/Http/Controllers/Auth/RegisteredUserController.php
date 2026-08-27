<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutMemoryActivityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

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

    public function store(RegisterUserRequest $request, CurrentUserResolver $users, WorkoutMemoryActivityLogger $activity): RedirectResponse
    {
        $wasFirstAccount = User::query()->doesntExist();

        try {
            $user = DB::transaction(function () use ($request, $users): User {
                $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

                $users->withProfile($user);
                event(new Registered($user));

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('register')
                ->withInput($request->safe()->only(['name', 'email']))
                ->withErrors([
                    'email' => 'We could not create your account. Please try again.',
                ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $activity->info('auth.registration.completed', [
            ...$activity->userContext($user),
            'email_hash' => $activity->emailHash($user->email),
            'was_first_account' => $wasFirstAccount,
        ], $request);

        return redirect()->route('verification.notice')
            ->with('status', 'Account created. Check your email to verify your address.');
    }

    private function registrationOpen(): bool
    {
        return User::query()->doesntExist() || (bool) config('workout_memory.registration.enabled');
    }
}
