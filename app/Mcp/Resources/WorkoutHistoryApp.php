<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('A read-only workout history browser with paginated session summaries and workout details.')]
#[AppMeta(prefersBorder: true)]
class WorkoutHistoryApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.workout-history-app', [
            'title' => $this->title(),
        ]);
    }
}
