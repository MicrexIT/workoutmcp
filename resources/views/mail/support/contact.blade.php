<x-mail::message>
# New support message

**Topic:** {{ $payload['topic_label'] }}

**From:** {{ $payload['name'] }} <{{ $payload['email'] }}>

@if ($payload['user_id'])
**Signed-in user:** #{{ $payload['user_id'] }} / {{ $payload['user_email'] }}
@else
**Signed-in user:** Guest
@endif

@if ($payload['context'])
**Context:** {{ $payload['context'] }}
@endif

<x-mail::panel>
{{ $payload['message'] }}
</x-mail::panel>

**IP:** {{ $payload['ip'] ?? 'unknown' }}

**User agent:** {{ $payload['user_agent'] ?? 'unknown' }}

Thanks,<br>
Workout Memory
</x-mail::message>
