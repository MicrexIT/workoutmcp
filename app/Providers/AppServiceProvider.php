<?php

namespace App\Providers;

use Illuminate\Http\Request;
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

        if (! $this->shouldUsePublicUrlForRequest($publicUrl, request())) {
            return;
        }

        URL::useOrigin($publicUrl);
        URL::forceScheme('https');
    }

    private function shouldUsePublicUrlForRequest(string $publicUrl, Request $request): bool
    {
        if (parse_url($publicUrl, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $publicHost = parse_url($publicUrl, PHP_URL_HOST);

        if (! is_string($publicHost) || $publicHost === '') {
            return false;
        }

        return $request->getHost() === $publicHost;
    }
}
