<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSupportRequest;
use App\Mail\SupportContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function show(Request $request): View
    {
        $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');
        $user = auth()->user();
        $context = $request->query('from');

        return view('support', [
            'publicUrl' => $publicUrl,
            'mcpUrl' => $publicUrl.'/mcp/workout-memory',
            'supportEmail' => (string) config('workout_memory.support.email'),
            'topics' => ContactSupportRequest::topicLabels(),
            'prefillName' => $user?->name,
            'prefillEmail' => $user?->email,
            'context' => is_string($context) ? $context : null,
        ]);
    }

    public function store(ContactSupportRequest $request): RedirectResponse
    {
        Mail::to((string) config('workout_memory.support.email'))
            ->queue(new SupportContactMessage($request->supportMessage()));

        return redirect()
            ->route('support')
            ->with('status', 'Message sent. We will reply by email.');
    }
}
