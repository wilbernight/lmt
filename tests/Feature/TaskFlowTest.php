<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_card_to_a_done_column_completes_it(): void
    {
        $ws = $this->workspace();
        $project = $this->project($ws, $ws->owner);
        $task = $this->task($ws, $ws->owner, $project);
        $done = $project->columns()->where('is_done', true)->first();

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$task->id}/move", ['column_id' => $done->id, 'position' => 10])->assertOk();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_a_card_cannot_be_moved_into_another_projects_column(): void
    {
        $ws = $this->workspace();
        $a = $this->project($ws, $ws->owner);
        $b = $this->project($ws, $ws->owner);
        $task = $this->task($ws, $ws->owner, $a);

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$task->id}/move", [
            'column_id' => $b->columns()->first()->id, 'position' => 1,
        ])->assertUnprocessable();
    }

    public function test_wip_limits_are_enforced(): void
    {
        $ws = $this->workspace();
        $project = $this->project($ws, $ws->owner);
        [$first, $second] = $project->columns()->take(2)->get();
        $second->update(['wip_limit' => 1]);

        $this->task($ws, $ws->owner, $project)->forceFill(['column_id' => $second->id])->save();
        $task = $this->task($ws, $ws->owner, $project);

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$task->id}/move", ['column_id' => $second->id, 'position' => 1])
            ->assertUnprocessable();
    }

    public function test_checklist_progress_and_comment_notifications(): void
    {
        $ws = $this->workspace();
        $assignee = $this->member($ws);
        $task = $this->task($ws, $ws->owner, null, [$assignee]);

        $item = $task->checklist()->create(['body' => 'مرحله', 'position' => 0]);
        $task->checklist()->create(['body' => 'مرحله دوم', 'position' => 1]);

        $this->actingAs($assignee)->patchJson("/w/{$ws->id}/tasks/{$task->id}/checklist/{$item->id}", ['is_done' => true])
            ->assertOk()->assertJson(['progress' => 50]);

        // Assignees may tick, but not reword, items.
        $this->actingAs($assignee)->patchJson("/w/{$ws->id}/tasks/{$task->id}/checklist/{$item->id}", ['body' => 'x'])->assertForbidden();

        $this->actingAs($assignee)->postJson("/w/{$ws->id}/tasks/{$task->id}/comments", ['body' => 'انجام شد', 'mentions' => [$ws->owner->id]])->assertCreated();
        $this->assertSame(1, $ws->owner->notifications()->count());
    }

    public function test_search_respects_visibility(): void
    {
        $ws = $this->workspace();
        $member = $this->member($ws);
        $private = $this->project($ws, $ws->owner);
        $task = $this->task($ws, $ws->owner, $private);
        $task->update(['title' => 'محرمانه بودجه']);

        $this->actingAs($ws->owner)->getJson("/w/{$ws->id}/search?q=بودجه")->assertJsonFragment(['title' => 'محرمانه بودجه']);
        $this->actingAs($member)->getJson("/w/{$ws->id}/search?q=بودجه")->assertJsonMissing(['title' => 'محرمانه بودجه']);
    }

    public function test_like_wildcards_in_search_are_escaped(): void
    {
        $ws = $this->workspace();
        $this->task($ws, $ws->owner)->update(['title' => 'گزارش']);

        $this->actingAs($ws->owner)->getJson("/w/{$ws->id}/search?q=%25%25")->assertExactJson([]);
        $this->assertSame(1, Task::count());
    }

    public function test_pages_render_for_a_workspace_owner(): void
    {
        $ws = $this->workspace();
        $project = $this->project($ws, $ws->owner);
        $this->task($ws, $ws->owner, $project, [$ws->owner]);

        foreach (['', '/tasks', '/calendar', '/projects', "/projects/{$project->id}", '/chat', '/letters', '/letters/create', '/notes', '/customers', '/monitoring', '/settings'] as $path) {
            $this->actingAs($ws->owner)->get("/w/{$ws->id}{$path}")->assertOk();
        }
    }
}
