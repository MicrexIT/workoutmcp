<?php

namespace App\Services\WorkoutMemory;

use Illuminate\Http\Request;
use Illuminate\Support\Uri;
use InvalidArgumentException;

final class OAuthLoginRedirect
{
    private const SessionKey = 'auth.oauth_redirect';

    public function __construct(private McpOAuthServer $oauth) {}

    public function flash(Request $request, mixed $url): void
    {
        if (! $this->isAuthorizationUrl($url)) {
            return;
        }

        $request->session()->flash(self::SessionKey, $url);
    }

    public function pull(Request $request): ?string
    {
        $url = $request->session()->pull(self::SessionKey);

        return $this->isAuthorizationUrl($url) ? $url : null;
    }

    private function isAuthorizationUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        try {
            $url = Uri::of($url);
            $authorizationUrl = Uri::of($this->oauth->publicUrl('/oauth/authorize'));
        } catch (InvalidArgumentException) {
            return false;
        }

        return $url->scheme() === $authorizationUrl->scheme()
            && $url->authority() === $authorizationUrl->authority()
            && $url->path() === $authorizationUrl->path()
            && $url->fragment() === null;
    }
}
