<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_can_be_assigned_to_a_person_without_an_account(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $task = $this->task($ws, $me, $this->project($ws, $me));

        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", [
            'people_names' => ['علیرضا کاظمی'],
        ])->assertOk();

        $task = $task->fresh(['people']);
        $this->assertCount(1, $task->people);
        $this->assertSame('علیرضا کاظمی', $task->people->first()->name);
        $this->assertSame($me->id, $task->people->first()->created_by);
    }

    public function test_the_same_person_is_reused_across_tasks(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);
        $a = $this->task($ws, $me, $project);
        $b = $this->task($ws, $me, $project);

        // The normaliser must fold spacing and ZWNJ differences into the same person.
        $this->actingAs($me)
            ->patchJson("/w/{$ws->id}/tasks/{$a->id}", ['people_names' => ['سارا محمدی']])
            ->assertOk();
        $this->actingAs($me)
            ->patchJson("/w/{$ws->id}/tasks/{$b->id}", ['people_names' => ['سارا  محمدی ']])
            ->assertOk();

        $this->assertSame(1, Person::count());
        $this->assertSame(
            $a->fresh()->people->first()->id,
            $b->fresh()->people->first()->id,
        );
    }

    public function test_moving_a_task_between_an_external_person_and_a_member(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $task = $this->task($ws, $me, $this->project($ws, $me));

        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['people_names' => ['مریم خارجی']])->assertOk();
        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['people_names' => [], 'assignees' => []])->assertOk();

        $this->assertCount(0, $task->fresh()->people);

        $other = $this->member($ws);
        $this->actingAs($me)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['people_names' => [], 'assignees' => [$other->id]])->assertOk();

        $task = $task->fresh();
        $this->assertCount(0, $task->people);
        $this->assertSame([$other->id], $task->assignees->pluck('id')->all());
    }

    public function test_the_following_tab_includes_unassigned_tasks(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);

        $this->task($ws, $me, $project); // a follow-up with nobody assigned
        $this->task($ws, $me, $project, [$me]); // still mine — must not be listed

        $response = $this->actingAs($me)->get("/w/{$ws->id}")->assertOk();

        $following = collect($response->viewData('page')['props']['following'] ?? []);
        $this->assertSame(1, $following->count());
        $this->assertTrue($following->contains(fn ($t) => $t['assignees'] === []), 'unassigned follow-up should be visible');
    }

    public function test_tasks_can_be_reordered_manually(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);
        $a = $this->task($ws, $me, $project);
        $b = $this->task($ws, $me, $project);
        $c = $this->task($ws, $me, $project);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/reorder", ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertTrue($c->fresh()->position < $a->fresh()->position);
        $this->assertTrue($a->fresh()->position < $b->fresh()->position);

        // The arranged order is what the tasks page serves by default.
        $response = $this->actingAs($me)->get("/w/{$ws->id}/tasks?tab=created")->assertOk();
        $order = collect($response->viewData('page')['props']['tasks'])->pluck('id');

        $this->assertSame([$c->id, $a->id, $b->id], $order->all());
    }

    public function test_a_partial_reorder_stays_inside_its_slots(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);
        $a = $this->task($ws, $me, $project);
        $b = $this->task($ws, $me, $project);
        $c = $this->task($ws, $me, $project);
        $d = $this->task($ws, $me, $project);

        // An arrangement to start from: a, b, c, d.
        $this->actingAs($me)->postJson("/w/{$ws->id}/tasks/reorder", ['ids' => [$a->id, $b->id, $c->id, $d->id]])->assertOk();

        // The dashboard shows a subset; only b and d are sent, rearranged.
        $this->actingAs($me)->postJson("/w/{$ws->id}/tasks/reorder", ['ids' => [$d->id, $b->id]])->assertOk();

        // They swap slots, but stay between their neighbours: a first, then d, c, b.
        $this->assertTrue($a->fresh()->position < $d->fresh()->position);
        $this->assertTrue($d->fresh()->position < $c->fresh()->position);
        $this->assertTrue($c->fresh()->position < $b->fresh()->position);
    }

    public function test_the_dashboard_serves_the_arranged_order(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $a = $this->task($ws, $me, null, [$me]);
        $b = $this->task($ws, $me, null, [$me]);
        $c = $this->task($ws, $me, null, [$me]);

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/reorder", ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $response = $this->actingAs($me)->get("/w/{$ws->id}")->assertOk();
        $order = collect($response->viewData('page')['props']['myTasks'])->pluck('id');

        $this->assertSame([$c->id, $a->id, $b->id], $order->all());
    }

    public function test_changing_a_tasks_column_keeps_its_place_in_the_list(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;
        $project = $this->project($ws, $me);
        $task = $this->task($ws, $me, $project);
        $target = $project->columns()->skip(1)->first();

        $this->actingAs($me)
            ->postJson("/w/{$ws->id}/tasks/{$task->id}/move", ['column_id' => $target->id])
            ->assertOk();

        $this->assertSame($target->id, $task->fresh()->column_id);
        $this->assertSame((float) $task->position, (float) $task->fresh()->position);
    }

    public function test_a_letter_can_be_sent_to_an_external_recipient_by_name_alone(): void
    {
        $ws = $this->workspace();
        $me = $ws->owner;

        $this->actingAs($me)->postJson("/w/{$ws->id}/letters", [
            'subject' => 'قرارداد همکاری',
            'body' => 'متن نامه',
            'priority' => 'normal',
            'to' => [],
            'to_names' => ['آقای شریفی — مدیر کلینیک حامی'],
            'cc_names' => ['دفتر بیمه'],
        ])->assertRedirect();

        $letter = $ws->letters()->first();

        // A free-typed name alone is a valid recipient; people are shared for both roles.
        $this->assertSame(['آقای شریفی — مدیر کلینیک حامی'], $letter->people()->wherePivot('type', 'to')->get()->pluck('name')->all());
        $this->assertSame(['دفتر بیمه'], $letter->people()->wherePivot('type', 'cc')->get()->pluck('name')->all());
        $this->assertSame(2, Person::inWorkspace($ws)->count());

        $print = $this->actingAs($me)->get("/w/{$ws->id}/letters/{$letter->id}/print")->assertOk();
        $print->assertSee('آقای شریفی — مدیر کلینیک حامی');
        $print->assertSee('دفتر بیمه');
    }
}
