<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\Label;
use App\Models\Letter;
use App\Models\Person;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** An upload backed by a real file with real bytes, so content sniffing sees them. */
    private function realFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function label(string $workspaceId, string $name, string $scope = 'task'): Label
    {
        $label = new Label(['name' => $name, 'color' => 'sky', 'scope' => $scope]);
        $label->workspace_id = $workspaceId;
        $label->save();

        return $label;
    }

    public function test_a_task_moves_to_another_project_of_the_same_workspace(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $a = $this->project($ws, $me);
        $b = $this->project($ws, $me);
        $task = $this->task($ws, $me, $a, [$me]);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/transfer", ['workspace_id' => $ws->id, 'project_id' => $b->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $task = $task->fresh();
        $this->assertSame($b->id, $task->project_id);
        $this->assertSame($b->columns()->first()->id, $task->column_id);
        $this->assertSame($ws->id, $task->workspace_id);
        $this->assertSame([$me->id], $task->assignees->pluck('id')->all());
        $this->assertSame('transferred', $task->activities()->latest('id')->first()->type);
    }

    public function test_staying_on_the_same_project_is_rejected(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);
        $task = $this->task($ws, $me, $project);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/transfer", ['workspace_id' => $ws->id, 'project_id' => $project->id])
            ->assertUnprocessable();
    }

    public function test_a_task_moves_to_another_workspace_remapping_people_labels_and_attachments(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sourceOnly = $this->member($ws);

        $to = $this->workspace();
        $to->addMember($me, WorkspaceRole::Member);

        // «طراحی» is a default category of every workspace, so it matches by name despite the different id.
        $shared = $this->label($ws->id, 'مشترک');
        $own = $this->label($ws->id, 'مخصوص مبدا');
        $twin = $this->label($to->id, 'مشترک');

        $project = $this->project($ws, $me);
        $task = $this->task($ws, $me, $project, [$me, $sourceOnly]);
        $task->labels()->sync([$shared->id, $own->id]);
        $task->forceFill(['category_id' => $ws->categories->firstWhere('name', 'طراحی')->id])->save();
        $task->people()->sync(Person::resolveMany($ws, ['شخص بیرونی'], $me->id));

        $attachmentId = $this->actingAs($me)->post("/w/{$ws->id}/attachments", [
            'file' => $this->realFile('note.txt', 'محتوای پرونده'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['attachments' => [$attachmentId]])->assertOk();

        $attachment = Attachment::find($attachmentId);
        $oldPath = $attachment->path;
        $bytes = $ws->fresh()->storage_used;

        $toProject = $this->project($to, $to->owner, [$me]);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/transfer", ['workspace_id' => $to->id, 'project_id' => $toProject->id])
            ->assertOk();

        $task = $task->fresh(['assignees', 'people', 'labels']);
        $this->assertSame($to->id, $task->workspace_id);
        $this->assertSame($toProject->id, $task->project_id);
        $this->assertSame($toProject->columns()->first()->id, $task->column_id);

        // Members of the destination keep their assignment; the source-only member drops off.
        $this->assertSame([$me->id], $task->assignees->pluck('id')->all());

        // The twin label survives; the source-only one does not. The default «طراحی» category matches by name.
        $this->assertSame([$twin->id], $task->labels->pluck('id')->all());
        $this->assertSame($to->categories->firstWhere('name', 'طراحی')->id, $task->category_id);

        // The external person is re-created by name inside the destination.
        $person = $task->people->first();
        $this->assertSame($to->id, $person->workspace_id);
        $this->assertSame('شخص بیرونی', $person->name);
        $this->assertSame(2, Person::count()); // one per workspace

        // The attachment file and its quota move together.
        $attachment = $attachment->fresh();
        $this->assertSame($to->id, $attachment->workspace_id);
        $this->assertStringStartsWith("attachments/{$to->id}/", $attachment->path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame(0, $ws->fresh()->storage_used);
        $this->assertSame($bytes, $to->fresh()->storage_used);
    }

    public function test_transfer_options_list_only_workspaces_where_the_member_can_contribute(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);

        $memberThere = $this->workspace();
        $memberThere->addMember($me, WorkspaceRole::Member);

        $guestThere = $this->workspace();
        $guestThere->addMember($me, WorkspaceRole::Guest);

        $suspended = $this->workspace();
        $suspended->addMember($me, WorkspaceRole::Member);
        $suspended->forceFill(['suspended_at' => now()])->save();

        $response = $this->actingAs($me)->getJson("/w/{$ws->id}/transfer/options")->assertOk();

        $ids = collect($response->json('workspaces'))->pluck('id');

        $this->assertContains($memberThere->id, $ids);
        $this->assertNotContains($guestThere->id, $ids);
        $this->assertNotContains($suspended->id, $ids);

        // Every listed workspace carries its projects for the picker.
        $listed = collect($response->json('workspaces'))->firstWhere('id', $ws->id);
        $this->assertSame([$project->id], collect($listed['projects'])->pluck('id')->all());
    }

    public function test_a_task_cannot_move_where_the_actor_is_a_guest(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $task = $this->task($ws, $me, $this->project($ws, $me), [$me]);

        $to = $this->workspace();
        $to->addMember($me, WorkspaceRole::Guest);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/transfer", ['workspace_id' => $to->id])
            ->assertForbidden();

        $this->assertSame($ws->id, $task->fresh()->workspace_id);
    }

    public function test_a_full_destination_refuses_the_transfer_without_touching_the_source(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $task = $this->task($ws, $me, $this->project($ws, $me));
        $task->people()->sync(Person::resolveMany($ws, ['شخص بیرونی'], $me->id));

        $attachmentId = $this->actingAs($me)->post("/w/{$ws->id}/attachments", [
            'file' => $this->realFile('note.txt', str_repeat('الف', 2000)),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['attachments' => [$attachmentId]])->assertOk();

        $to = $this->workspace();
        $to->addMember($me, WorkspaceRole::Member);
        $to->forceFill(['storage_limit' => Attachment::find($attachmentId)->size - 1])->save();

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/transfer", ['workspace_id' => $to->id])
            ->assertUnprocessable();

        $task = $task->fresh();
        $this->assertSame($ws->id, $task->workspace_id);
        $this->assertStringStartsWith("attachments/{$ws->id}/", Attachment::find($attachmentId)->path);
        Storage::disk('local')->assertExists(Attachment::find($attachmentId)->path);
    }

    public function test_a_letter_moves_to_another_workspace_renumbered_and_detached_from_its_thread(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);
        $sourceOnly = $this->member($ws);

        $to = $this->workspace();
        $to->addMember($me, WorkspaceRole::Member);
        $to->addMember($sara, WorkspaceRole::Member);
        $toMember = $this->member($to);

        $this->actingAs($toMember)->post("/w/{$to->id}/letters", [
            'subject' => 'نامه قبلی', 'body' => 'متن', 'priority' => 'normal', 'to' => [$to->owner->id],
        ])->assertRedirect();

        $this->actingAs($me)->post("/w/{$ws->id}/letters", [
            'subject' => 'نامه رسمی', 'body' => 'متن', 'priority' => 'normal',
            'to' => [$sara->id, $sourceOnly->id], 'to_names' => ['گیرنده بیرونی'],
        ])->assertRedirect();

        $letter = $ws->letters()->firstOrFail();
        $this->actingAs($sara)->post("/w/{$ws->id}/letters/{$letter->id}/reply", ['body' => 'پاسخ'])->assertRedirect();
        $replyId = $ws->letters()->where('reply_to_id', $letter->id)->first()->id;

        $this->actingAs($sara) // a recipient, but neither the sender nor a source admin
            ->postJson("/w/{$ws->id}/letters/{$letter->id}/transfer", ['workspace_id' => $to->id])
            ->assertForbidden();

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/letters/{$letter->id}/transfer", ['workspace_id' => $to->id])
            ->assertOk();

        $letter = $letter->fresh(['recipients', 'people']);
        $this->assertSame($to->id, $letter->workspace_id);
        $this->assertSame(2, $letter->number); // continues after the destination's own letter
        $this->assertSame([$sara->id], $letter->recipients->pluck('id')->all()); // the source-only member drops off

        // The external recipient is re-created by name, keeping the to/cc type.
        $person = $letter->people->first();
        $this->assertSame($to->id, $person->workspace_id);
        $this->assertSame('to', $person->pivot->type);

        // The reply stays behind as a standalone letter.
        $reply = Letter::find($replyId);
        $this->assertNull($reply->reply_to_id);
        $this->assertSame($ws->id, $reply->workspace_id);
        $this->assertSame(0, Letter::where('reply_to_id', $letter->id)->count()); // nothing followed the letter
    }

    public function test_a_minute_moves_to_another_workspace_with_participants_and_actions(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);

        $to = $this->workspace();
        $to->addMember($me, WorkspaceRole::Member);
        $to->addMember($sara, WorkspaceRole::Member);

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", [
            'subject' => 'جلسه هم‌راستایی',
            'body' => 'متن',
            'held_at' => now()->subDay()->toIso8601String(),
            'location' => 'اتاق ۱',
            'participants' => [
                ['user_id' => $sara->id, 'attended' => true],
                ['user_id' => null, 'name' => 'مهندس طاهری', 'attended' => false],
            ],
            'actions' => [
                ['title' => 'تهیه گزارش', 'name' => 'مهندس طاهری'],
                ['title' => 'بازبینی طرح', 'user_id' => $sara->id],
            ],
        ])->assertRedirect();

        $minute = $ws->minutes()->firstOrFail();
        $action = $minute->actions->firstWhere('title', 'تهیه گزارش');

        // The action became a task in this workspace; the task must stay behind.
        $this->actingAs($me)->post("/w/{$ws->id}/minutes/{$minute->id}/actions/{$action->id}/to-task")->assertRedirect();
        $taskId = $action->fresh()->task_id;
        $this->assertNotNull($taskId);

        $this->actingAs($sara) // a participant, but not the organizer or an admin
            ->postJson("/w/{$ws->id}/minutes/{$minute->id}/transfer", ['workspace_id' => $to->id])
            ->assertForbidden();

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/minutes/{$minute->id}/transfer", ['workspace_id' => $to->id])
            ->assertOk();

        $minute = $minute->fresh(['participants.person', 'actions.person']);
        $this->assertSame($to->id, $minute->workspace_id);
        $this->assertSame(1, $minute->number); // numbered afresh in the destination

        // Attendance travels with the invitees.
        $memberRow = $minute->participants->whereNotNull('user_id')->first();
        $this->assertSame($sara->id, $memberRow->user_id);
        $this->assertTrue($memberRow->attended);

        $personRow = $minute->participants->whereNotNull('person_id')->first();
        $this->assertFalse($personRow->attended);
        $this->assertSame($to->id, $personRow->person->workspace_id);

        // Action items survive; the responsible person is re-created there and the task link is dropped.
        $report = $minute->actions->firstWhere('title', 'تهیه گزارش');
        $this->assertSame($to->id, $report->person->workspace_id);
        $this->assertNull($report->task_id);

        $review = $minute->actions->firstWhere('title', 'بازبینی طرح');
        $this->assertSame($sara->id, $review->user_id); // she is a member of the destination too

        $this->assertSame($ws->id, Task::find($taskId)->workspace_id); // the created task stayed behind
    }

    public function test_letters_and_minutes_refuse_to_move_within_their_own_workspace(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $someone = $this->member($ws);

        $this->actingAs($me)->post("/w/{$ws->id}/letters", [
            'subject' => 'نامه', 'body' => 'متن', 'priority' => 'normal', 'to' => [$someone->id],
        ])->assertRedirect();
        $letter = $ws->letters()->first();

        $this->actingAs($me)->postJson("/w/{$ws->id}/letters/{$letter->id}/transfer", ['workspace_id' => $ws->id])->assertUnprocessable();

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", ['subject' => 'جلسه', 'body' => 'متن'])->assertRedirect();
        $minute = $ws->minutes()->first();

        $this->actingAs($me)->postJson("/w/{$ws->id}/minutes/{$minute->id}/transfer", ['workspace_id' => $ws->id])->assertUnprocessable();
    }
}
