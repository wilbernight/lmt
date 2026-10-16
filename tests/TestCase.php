<?php

namespace Tests;

use App\Actions\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Act as the user as loaded from the database, the way a real request sees it. */
    public function actingAs($user, $guard = null)
    {
        return parent::actingAs($user instanceof User && $user->exists ? $user->fresh() : $user, $guard);
    }

    protected function workspace(?User $owner = null): Workspace
    {
        return app(CreateWorkspace::class)($owner ?? User::factory()->create(), 'تیم تست');
    }

    protected function member(Workspace $workspace, WorkspaceRole $role = WorkspaceRole::Member): User
    {
        $user = User::factory()->create();
        $workspace->addMember($user, $role);

        return $user;
    }

    protected function project(Workspace $workspace, User $creator, array $members = [], string $visibility = 'members'): Project
    {
        $project = new Project(['name' => 'پروژه', 'color' => 'indigo', 'visibility' => $visibility]);
        $project->workspace_id = $workspace->id;
        $project->created_by = $creator->id;
        $project->save();
        $project->createDefaultColumns();
        $project->members()->sync(
            collect($members)->mapWithKeys(fn (User $u) => [$u->id => ['role' => ProjectRole::Member->value]])
                ->put($creator->id, ['role' => ProjectRole::Manager->value])
        );

        return $project;
    }

    protected function task(Workspace $workspace, User $creator, ?Project $project = null, array $assignees = []): Task
    {
        $task = new Task(['title' => 'وظیفه آزمایشی']);
        $task->workspace_id = $workspace->id;
        $task->creator_id = $creator->id;
        $task->project_id = $project?->id;
        $task->column_id = $project?->columns()->first()?->id;
        $task->save();
        $task->assignees()->sync(collect($assignees)->pluck('id'));

        return $task;
    }
}
