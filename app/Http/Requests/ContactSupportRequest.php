<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactSupportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'topic' => ['required', 'string', Rule::in(array_keys(self::topicLabels()))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'context' => ['nullable', 'string', 'max:255'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, email: string, topic: string, topic_label: string, message: string, context: string|null, user_id: int|null, user_email: string|null, ip: string|null, user_agent: string|null}
     */
    public function supportMessage(): array
    {
        $validated = $this->validated();
        $topic = (string) $validated['topic'];

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'topic' => $topic,
            'topic_label' => self::topicLabels()[$topic],
            'message' => (string) $validated['message'],
            'context' => $validated['context'] ?? null,
            'user_id' => $this->user()?->id,
            'user_email' => $this->user()?->email,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function topicLabels(): array
    {
        return [
            'connection' => 'Connection help',
            'account' => 'Account or data',
            'workout' => 'Workout correction',
            'bug' => 'Bug report',
            'security' => 'Security issue',
            'other' => 'Something else',
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->trimmedInput('email');

        $this->merge([
            'name' => $this->trimmedInput('name'),
            'email' => is_string($email) ? Str::lower($email) : null,
            'topic' => $this->trimmedInput('topic'),
            'message' => $this->trimmedInput('message'),
            'context' => $this->trimmedInput('context'),
        ]);
    }

    private function trimmedInput(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : null;
    }
}
