<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

        if (parse_url($publicUrl, PHP_URL_SCHEME) !== 'https') {
            return;
        }

        URL::forceRootUrl($publicUrl);
        URL::forceScheme('https');
    }
}
