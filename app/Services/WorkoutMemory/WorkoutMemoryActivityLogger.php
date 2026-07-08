<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkoutMemoryActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = [], ?Request $request = null): void
    {
        Log::info($this->eventName($event), $this->withRequestContext($context, $request));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = [], ?Request $request = null): void
    {
        Log::warning($this->eventName($event), $this->withRequestContext($context, $request));
    }

    public function emailHash(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email === '' ? null : $this->hash($email);
    }

    public function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : hash('sha256', $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function userContext(?User $user): array
    {
        return [
            'user_id' => $user?->getAuthIdentifier(),
            'user_email_hash' => $this->emailHash($user?->email),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function oauthClientContext(?string $clientId, ?string $redirectUri = null, ?string $clientName = null): array
    {
        return [
            'client_id_hash' => $this->hash($clientId),
            'client_host' => $this->uriHost($clientId),
            'client_name' => $this->safeLabel($clientName),
            'redirect_destination' => $this->redirectDestination($redirectUri),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function workoutInputContext(array $input): array
    {
        return [
            'idempotency_key_hash' => $this->hash($this->stringOrNull($input['idempotency_key'] ?? null)),
            'source_message_id_hash' => $this->hash($this->stringOrNull($input['source_message_id'] ?? null)),
            'has_raw_input' => isset($input['raw_input']) && trim((string) $input['raw_input']) !== '',
            'target_session' => $this->stringOrNull($input['target_session'] ?? null),
            'requested_workout_id' => isset($input['workout_id']) ? (int) $input['workout_id'] : null,
            'exercise_count' => is_countable($input['exercises'] ?? null)
                ? count($input['exercises'])
                : (isset($input['exercise']) && is_array($input['exercise']) ? 1 : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    public function sessionSummaryContext(array $session, string $key = 'workout'): array
    {
        return [
            "{$key}_id" => $session['id'] ?? null,
            "{$key}_status" => $this->stringOrNull($session['status'] ?? null),
            "{$key}_exercise_count" => is_countable($session['exercises'] ?? null) ? count($session['exercises']) : null,
            "{$key}_set_count" => $session['set_count'] ?? null,
        ];
    }

    private function eventName(string $event): string
    {
        return 'workout_memory.'.trim($event, '.');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withRequestContext(array $context, ?Request $request): array
    {
        if ($request === null) {
            return $this->pruneNullValues($context);
        }

        return $this->pruneNullValues([
            ...$context,
            'request_method' => $request->method(),
            'request_path' => '/'.ltrim($request->path(), '/'),
            'request_ip' => $request->ip(),
            'request_user_agent_hash' => $this->hash($request->userAgent()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function pruneNullValues(array $context): array
    {
        return collect($context)
            ->reject(fn (mixed $value): bool => $value === null)
            ->all();
    }

    private function safeLabel(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 120, '');
    }

    /**
     * @return array<string, string>|null
     */
    private function redirectDestination(?string $redirectUri): ?array
    {
        $redirectUri = $this->stringOrNull($redirectUri);

        if ($redirectUri === null) {
            return null;
        }

        $scheme = Str::lower((string) parse_url($redirectUri, PHP_URL_SCHEME));
        $host = Str::lower((string) parse_url($redirectUri, PHP_URL_HOST));

        if ($scheme === 'https') {
            return ['type' => 'web', 'target' => $host];
        }

        if ($scheme === 'http') {
            $port = parse_url($redirectUri, PHP_URL_PORT);

            return ['type' => 'loopback', 'target' => $host.(is_int($port) ? ':'.$port : '')];
        }

        return ['type' => 'native', 'target' => $scheme.'://'];
    }

    private function uriHost(?string $uri): ?string
    {
        $host = parse_url((string) $uri, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::lower($host) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
