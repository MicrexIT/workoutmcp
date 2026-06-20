<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureRateLimiting();

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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('public', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('public_per_minute'))
            ->by($this->requestLimitKey($request, 'public')));

        RateLimiter::for('authenticated-web', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('authenticated_web_per_minute'))
            ->by($this->requestLimitKey($request, 'authenticated-web')));

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('login_per_minute'))
                ->by('login-ip:'.$this->requestIp($request)),
            Limit::perMinute($this->rateLimit('login_email_per_minute'))
                ->by('login-email:'.$this->hashedInput($request, 'email').':'.$this->requestIp($request)),
        ]);

        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('registration_per_minute'))
                ->by('registration-minute:'.$this->requestIp($request)),
            Limit::perHour($this->rateLimit('registration_per_hour'))
                ->by('registration-hour:'.$this->requestIp($request)),
        ]);

        RateLimiter::for('contact', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('contact_per_minute'))
                ->by('contact-minute:'.$this->requestLimitKey($request, 'contact')),
            Limit::perHour($this->rateLimit('contact_per_hour'))
                ->by('contact-hour:'.$this->requestLimitKey($request, 'contact')),
        ]);

        RateLimiter::for('email-verification', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('email_verification_per_minute'))
            ->by($this->requestLimitKey($request, 'email-verification')));

        RateLimiter::for('oauth-metadata', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('oauth_metadata_per_minute'))
            ->by($this->requestLimitKey($request, 'oauth-metadata')));

        RateLimiter::for('oauth-register', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('oauth_register_per_minute'))
                ->by('oauth-register-minute:'.$this->requestIp($request)),
            Limit::perHour($this->rateLimit('oauth_register_per_hour'))
                ->by('oauth-register-hour:'.$this->requestIp($request)),
        ]);

        RateLimiter::for('oauth-authorize', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('oauth_authorize_per_minute'))
            ->by($this->requestLimitKey($request, 'oauth-authorize')));

        RateLimiter::for('oauth-token', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('oauth_token_per_minute'))
                ->by('oauth-token-ip:'.$this->requestIp($request)),
            Limit::perMinute($this->rateLimit('oauth_token_client_per_minute'))
                ->by('oauth-token-client:'.$this->hashedInput($request, 'client_id').':'.$this->requestIp($request)),
        ]);

        RateLimiter::for('mcp-unauthenticated', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('mcp_unauthenticated_per_minute'))
            ->by('mcp-unauthenticated:'.$this->requestIp($request)));

        RateLimiter::for('mcp', fn (Request $request): array => [
            Limit::perMinute($this->rateLimit('mcp_per_minute'))
                ->by('mcp-minute:'.$this->requestLimitKey($request, 'mcp')),
            Limit::perHour($this->rateLimit('mcp_per_hour'))
                ->by('mcp-hour:'.$this->requestLimitKey($request, 'mcp')),
        ]);
    }

    private function rateLimit(string $key): int
    {
        return max(1, (int) config("workout_memory.rate_limits.{$key}", 60));
    }

    private function requestLimitKey(Request $request, string $scope): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return $scope.':user:'.$userId;
        }

        return $scope.':ip:'.$this->requestIp($request);
    }

    private function requestIp(Request $request): string
    {
        return $request->ip() ?? 'unknown';
    }

    private function hashedInput(Request $request, string $field): string
    {
        $value = Str::lower(trim((string) $request->input($field, '')));

        return $value === '' ? 'missing' : sha1($value);
    }
}
