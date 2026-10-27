<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinuteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'subject' => 'جلسه هم‌راستایی هفتگی',
            'body' => 'شرح مذاکرات جلسه',
            'held_at' => now()->subDay()->setTime(11, 0)->toIso8601String(),
            'location' => 'اتاق جلسه ۱',
            'participants' => [
                ['user_id' => null, 'name' => 'مهندس طاهری', 'attended' => true],
            ],
            'actions' => [
                ['title' => 'تهیه گزارش', 'name' => 'مهندس طاهری', 'due_at' => now()->addDays(3)->toIso8601String()],
                ['title' => 'بازبینی طرح', 'user_id' => null],
            ],
            ...$overrides,
        ];
    }

    public function test_a_member_can_record_a_minute_with_external_participants(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);

        $this->actingAs($me)
            ->post("/w/{$ws->id}/minutes", $this->payload([
                'participants' => [
                    ['user_id' => $sara->id, 'attended' => true],
                    ['name' => 'مهندس طاهری', 'attended' => false],
                ],
            ]))
            ->assertRedirect();

        $minute = $ws->minutes()->first();
        $this->assertSame(1, $minute->number);
        $this->assertSame('draft', $minute->status);

        // The free-typed participant became a Person of this workspace.
        $person = Person::inWorkspace($ws)->first();
        $this->assertSame('مهندس طاهری', $person->name);

        $participants = $minute->participants()->get();
        $this->assertSame([$sara->id, null], $participants->pluck('user_id')->all());
        $this->assertSame([null, $person->id], $participants->pluck('person_id')->all());

        // Show returns the full payload with participants and actions.
        $response = $this->actingAs($me)->get("/w/{$ws->id}/minutes/{$minute->id}")->assertOk();
        $props = $response->viewData('page')['props']['minute'];
        $this->assertSame('جلسه هم‌راستایی هفتگی', $props['subject']);
        $this->assertCount(2, $props['participants']);
        $this->assertSame('تهیه گزارش', $props['actions'][0]['title']);
        $this->assertSame($person->id, $props['actions'][0]['person']['id']);
    }

    public function test_minutes_are_numbered_per_workspace(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload())->assertRedirect();
        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload(['subject' => 'جلسه دوم']))->assertRedirect();

        $this->assertSame([1, 2], $ws->minutes()->orderBy('number')->pluck('number')->all());

        // A different workspace starts from one again.
        $other = $this->workspace();
        $this->actingAs($other->owner)->post("/w/{$other->id}/minutes", $this->payload())->assertRedirect();
        $this->assertSame(1, $other->minutes()->first()->number);
    }

    public function test_a_final_minute_is_read_only_but_actions_still_convert(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload(['participants' => [['user_id' => $sara->id]]]))->assertRedirect();
        $minute = $ws->minutes()->first();

        $this->actingAs($me)->post("/w/{$ws->id}/minutes/{$minute->id}/finalize")->assertRedirect();
        $this->assertSame('final', $minute->fresh()->status);

        // Editing is closed after finalising...
        $this->actingAs($me)->put("/w/{$ws->id}/minutes/{$minute->id}", $this->payload(['subject' => 'ویرایش ممنوع']))->assertForbidden();

        // ...but converting an action into a task still works, even when final.
        $action = $minute->actions()->first();
        $this->actingAs($me)->post("/w/{$ws->id}/minutes/{$minute->id}/actions/{$action->id}/to-task")->assertRedirect();

        $task = $action->fresh()->task;
        $this->assertNotNull($task);
        $this->assertSame('تهیه گزارش', $task->title);
        $this->assertSame($action->person_id, $task->people->first()?->id);
        $this->assertSame($ws->id, $task->workspace_id);

        // And only once.
        $this->actingAs($me)->post("/w/{$ws->id}/minutes/{$minute->id}/actions/{$action->id}/to-task")->assertStatus(422);
    }

    public function test_attending_members_sign_off_the_final_minute(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);
        $reza = $this->member($ws); // invited but absent

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload([
            'participants' => [['user_id' => $sara->id, 'attended' => true], ['user_id' => $reza->id, 'attended' => false]],
        ]))->assertRedirect();
        $minute = $ws->minutes()->first();

        // Approval only makes sense on the final version.
        $this->actingAs($sara)->post("/w/{$ws->id}/minutes/{$minute->id}/approve")->assertForbidden();

        $this->actingAs($me)->post("/w/{$ws->id}/minutes/{$minute->id}/finalize")->assertRedirect();

        // Absent invitees do not sign.
        $this->actingAs($reza)->post("/w/{$ws->id}/minutes/{$minute->id}/approve")->assertForbidden();
        $this->actingAs($sara)->post("/w/{$ws->id}/minutes/{$minute->id}/approve")->assertRedirect();

        $approvals = $minute->approvals()->pluck('users.id');
        $this->assertContains($sara->id, $approvals);
        $this->assertNotContains($reza->id, $approvals);

        // Toggling off works too.
        $this->actingAs($sara)->post("/w/{$ws->id}/minutes/{$minute->id}/approve")->assertRedirect();
        $this->assertNotContains($sara->id, $minute->approvals()->pluck('users.id'));
    }

    public function test_guests_and_uninvited_members_cannot_open_minutes(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $guest = $this->member($ws, WorkspaceRole::Guest);
        $stranger = $this->member($ws);

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload())->assertRedirect();
        $minute = $ws->minutes()->first();

        $this->actingAs($guest)->get("/w/{$ws->id}/minutes")->assertForbidden();
        $this->actingAs($guest)->get("/w/{$ws->id}/minutes/{$minute->id}")->assertForbidden();
        $this->actingAs($guest)->post("/w/{$ws->id}/minutes", $this->payload())->assertForbidden();
        $this->actingAs($stranger)->get("/w/{$ws->id}/minutes/{$minute->id}")->assertForbidden();

        // An attendee sees it in their list, a stranger does not.
        $sara = $this->member($ws);
        $minute->participants()->create(['user_id' => $sara->id, 'attended' => true]);

        $this->actingAs($sara)->get("/w/{$ws->id}/minutes")->assertOk();
        $this->assertCount(1, $this->actingAs($sara)->get("/w/{$ws->id}/minutes")->viewData('page')['props']['minutes']['data']);
        $this->assertEmpty($this->actingAs($stranger)->get("/w/{$ws->id}/minutes")->assertOk()->viewData('page')['props']['minutes']['data']);
    }

    public function test_the_minute_prints_on_the_company_letterhead(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $sara = $this->member($ws);

        $this->actingAs($me)->post("/w/{$ws->id}/minutes", $this->payload([
            'participants' => [['user_id' => $sara->id, 'attended' => true], ['name' => 'مهندس طاهری', 'attended' => false]],
        ]))->assertRedirect();
        $minute = $ws->minutes()->first();

        $print = $this->actingAs($me)->get("/w/{$ws->id}/minutes/{$minute->id}/print")->assertOk();
        $print->assertSee('صورت‌جلسه');
        $print->assertSee('جلسه هم‌راستایی هفتگی');
        $print->assertSee('مهندس طاهری');
        $print->assertSee('اقدامات');
        $print->assertSee('امضاها');
    }
}
