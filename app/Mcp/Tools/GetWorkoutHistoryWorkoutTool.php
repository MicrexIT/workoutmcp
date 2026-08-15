<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\WorkoutHistoryApp;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

#[Name('get_workout_history_workout')]
#[Title('Open Workout Details')]
#[Description('Return one full logged workout for the Workout History UI app.')]
#[RendersApp(resource: WorkoutHistoryApp::class, visibility: [Visibility::App])]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetWorkoutHistoryWorkoutTool extends GetWorkoutTool
{
    //
}
