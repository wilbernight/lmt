<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Conversation;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_from_another_workspace_cannot_be_reached_through_my_workspace(): void
    {
        $mine = $this->workspace();
        $me = $mine->owner;
        $theirs = $this->workspace();
        $secret = $this->task($theirs, $theirs->owner);

        // Scoped binding: the task id is looked up inside the workspace in the URL.
        $this->actingAs($me)->getJson("/w/{$mine->id}/tasks/{$secret->id}")->assertNotFound();
        $this->actingAs($me)->patchJson("/w/{$mine->id}/tasks/{$secret->id}", ['title' => 'hacked'])->assertNotFound();
        $this->assertSame('وظیفه آزمایشی', $secret->fresh()->title);
    }

    public function test_members_cannot_see_private_projects_or_their_tasks(): void
    {
        $ws = $this->workspace();
        $insider = $this->member($ws);
        $outsider = $this->member($ws);
        $project = $this->project($ws, $ws->owner, [$insider]);
        $task = $this->task($ws, $ws->owner, $project);

        $this->actingAs($insider)->get("/w/{$ws->id}/projects/{$project->id}")->assertOk();
        $this->actingAs($outsider)->get("/w/{$ws->id}/projects/{$project->id}")->assertForbidden();
        $this->actingAs($outsider)->getJson("/w/{$ws->id}/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($outsider)->postJson("/w/{$ws->id}/tasks/{$task->id}/comments", ['body' => 'x'])->assertForbidden();
    }

    public function test_assignees_can_work_on_but_not_delete_a_task(): void
    {
        $ws = $this->workspace();
        $assignee = $this->member($ws);
        $task = $this->task($ws, $ws->owner, null, [$assignee]);

        $this->actingAs($assignee)->postJson("/w/{$ws->id}/tasks/{$task->id}/complete")->assertOk();
        $this->actingAs($assignee)->deleteJson("/w/{$ws->id}/tasks/{$task->id}")->assertForbidden();
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_guests_cannot_create_projects_or_open_customers(): void
    {
        $ws = $this->workspace();
        $guest = $this->member($ws, WorkspaceRole::Guest);

        $this->actingAs($guest)->post("/w/{$ws->id}/projects", ['name' => 'p', 'color' => 'indigo', 'visibility' => 'members'])->assertForbidden();
        $this->actingAs($guest)->get("/w/{$ws->id}/customers")->assertForbidden();
        $this->actingAs($guest)->postJson("/w/{$ws->id}/tasks", ['title' => 'x'])->assertForbidden();
    }

    public function test_only_workspace_members_can_be_assigned(): void
    {
        $ws = $this->workspace();
        $stranger = User::factory()->create();

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks", [
            'title' => 'کار', 'assignees' => [$stranger->id, $ws->owner->id],
        ])->assertCreated();

        $task = Task::latest()->first();
        $this->assertSame([$ws->owner->id], $task->assignees()->pluck('users.id')->all());
    }

    public function test_server_controlled_fields_cannot_be_mass_assigned(): void
    {
        $ws = $this->workspace();
        $other = $this->workspace();
        $member = $this->member($ws);

        $this->actingAs($member)->postJson("/w/{$ws->id}/tasks", [
            'title' => 'کار', 'creator_id' => $ws->owner->id, 'workspace_id' => $other->id, 'completed_at' => now(),
        ])->assertCreated();

        $task = Task::latest()->first();
        $this->assertSame($member->id, $task->creator_id);
        $this->assertSame($ws->id, $task->workspace_id);
        $this->assertNull($task->completed_at);
    }

    public function test_notes_are_private_to_their_author(): void
    {
        $ws = $this->workspace();
        $colleague = $this->member($ws);
        $note = new Note(['title' => 'محرمانه']);
        $note->workspace_id = $ws->id;
        $note->user_id = $ws->owner->id;
        $note->save();

        $this->actingAs($colleague)->patchJson("/w/{$ws->id}/notes/{$note->id}", ['title' => 'x'])->assertForbidden();
        $this->actingAs($colleague)->delete("/w/{$ws->id}/notes/{$note->id}")->assertForbidden();
    }

    public function test_letters_are_visible_only_to_sender_and_recipients(): void
    {
        $ws = $this->workspace();
        $to = $this->member($ws);
        $bystander = $this->member($ws);

        $this->actingAs($ws->owner)->post("/w/{$ws->id}/letters", [
            'subject' => 'قرارداد', 'body' => 'متن', 'priority' => 'normal', 'to' => [$to->id],
        ])->assertRedirect();

        $letter = $ws->letters()->first();

        $this->actingAs($to)->get("/w/{$ws->id}/letters/{$letter->id}")->assertOk();
        $this->actingAs($bystander)->get("/w/{$ws->id}/letters/{$letter->id}")->assertForbidden();
        $this->assertSame(1, $letter->number);
    }

    public function test_conversations_are_limited_to_participants(): void
    {
        $ws = $this->workspace();
        $a = $this->member($ws);
        $b = $this->member($ws);

        $this->actingAs($a)->post("/w/{$ws->id}/chat/direct", ['user_id' => $b->id])->assertRedirect();
        $conversation = Conversation::firstOrFail();

        $this->actingAs($b)->postJson("/w/{$ws->id}/chat/{$conversation->id}/messages", ['body' => 'سلام'])->assertCreated();
        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/chat/{$conversation->id}/messages", ['body' => 'نفوذ'])->assertForbidden();
        $this->actingAs($ws->owner)->get("/w/{$ws->id}/chat/{$conversation->id}")->assertForbidden();
    }

    public function test_admins_cannot_promote_others_to_admin_or_touch_the_owner(): void
    {
        $ws = $this->workspace();
        $admin = $this->member($ws, WorkspaceRole::Admin);
        $member = $this->member($ws);

        $this->actingAs($admin)->put("/w/{$ws->id}/members/{$member->id}", ['role' => 'admin'])->assertForbidden();
        $this->actingAs($admin)->delete("/w/{$ws->id}/members/{$ws->owner->id}")->assertForbidden();
        $this->actingAs($member)->put("/w/{$ws->id}/members/{$admin->id}", ['role' => 'guest'])->assertForbidden();
        $this->actingAs($ws->owner)->put("/w/{$ws->id}/members/{$member->id}", ['role' => 'owner'])->assertSessionHasErrors('role');
    }

    public function test_invitations_only_work_for_the_invited_address(): void
    {
        $ws = $this->workspace();
        $this->actingAs($ws->owner)->post("/w/{$ws->id}/invitations", ['email' => 'invitee@example.com', 'role' => 'member'])->assertRedirect();

        $invitation = $ws->invitations()->first();
        $this->assertNotNull($invitation);
        $this->assertSame(64, strlen($invitation->token_hash), 'token is stored hashed');

        // The plain token is never stored, so a guessed hash does not work.
        $wrongUser = User::factory()->create(['email' => 'other@example.com']);
        $this->actingAs($wrongUser)->post('/invitations/'.str_repeat('a', 64))->assertNotFound();
    }
}
