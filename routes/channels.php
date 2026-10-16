<?php

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

Broadcast::channel('conversations.{conversation}', function ($user, string $conversation) {
    $model = Conversation::find($conversation);

    return $model !== null && $user->can('view', $model);
});

Broadcast::channel('projects.{project}', function ($user, string $project) {
    $model = Project::find($project);

    return $model !== null && $user->can('view', $model);
});

// Presence for "who is online" inside a workspace.
Broadcast::channel('workspaces.{workspace}', function ($user, string $workspace) {
    return $user->belongsToWorkspace($workspace) ? ['id' => $user->id, 'name' => $user->name] : false;
});
