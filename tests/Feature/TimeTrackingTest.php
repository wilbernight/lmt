<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_starts_and_stops_a_timer(): void
    {
        $ws = $this->workspace();
        $worker = $this->member($ws);
        $task = $this->task($ws, $ws->owner, null, [$worker]);

        $this->actingAs($worker)->postJson("/w/{$ws->id}/tasks/{$task->id}/timer")->assertCreated()
            ->assertJsonPath('timer.task_id', $task->id);

        $this->travel(25)->minutes();

        $this->actingAs($worker)->postJson('/timer/stop')->assertOk();

        $entry = TimeEntry::firstOrFail();
        $this->assertFalse($entry->isRunning());
        $this->assertEqualsWithDelta(25 * 60, $entry->seconds, 2);
    }

    public function test_outsiders_cannot_track_time_on_a_task(): void
    {
        $ws = $this->workspace();
        $outsider = $this->member($ws);
        $project = $this->project($ws, $ws->owner);
        $task = $this->task($ws, $ws->owner, $project);

        $this->actingAs($outsider)->postJson("/w/{$ws->id}/tasks/{$task->id}/timer")->assertForbidden();
        $this->actingAs($outsider)->postJson("/w/{$ws->id}/tasks/{$task->id}/time", ['started_at' => now()->subHour(), 'minutes' => 30])->assertForbidden();

        $other = $this->workspace();
        $this->actingAs($other->owner)->postJson("/w/{$other->id}/tasks/{$task->id}/timer")->assertNotFound();
    }

    public function test_starting_a_new_timer_stops_the_previous_one(): void
    {
        $ws = $this->workspace();
        $a = $this->task($ws, $ws->owner, null, [$ws->owner]);
        $b = $this->task($ws, $ws->owner, null, [$ws->owner]);

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$a->id}/timer")->assertCreated();
        $this->travel(10)->minutes();
        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$b->id}/timer")->assertCreated();

        $this->assertSame(1, TimeEntry::running()->count());
        $this->assertSame($b->id, TimeEntry::running()->first()->task_id);
        $this->assertEqualsWithDelta(600, TimeEntry::where('task_id', $a->id)->first()->seconds, 2);
    }

    public function test_manual_entries_are_validated(): void
    {
        $ws = $this->workspace();
        $task = $this->task($ws, $ws->owner, null, [$ws->owner]);
        $url = "/w/{$ws->id}/tasks/{$task->id}/time";

        $this->actingAs($ws->owner)->postJson($url, ['started_at' => now()->addHour(), 'minutes' => 30])->assertUnprocessable();
        $this->actingAs($ws->owner)->postJson($url, ['started_at' => now()->subMinutes(10), 'minutes' => 60])->assertUnprocessable();
        $this->actingAs($ws->owner)->postJson($url, ['started_at' => now()->subDays(1), 'minutes' => 17 * 60])->assertUnprocessable();
        $this->actingAs($ws->owner)->postJson($url, ['started_at' => now()->subHours(3), 'minutes' => 90, 'note' => 'طراحی'])->assertCreated();

        $this->assertSame(90 * 60, TimeEntry::firstOrFail()->seconds);
    }

    public function test_project_total_adds_up_everyones_hours(): void
    {
        $ws = $this->workspace();
        $dev = $this->member($ws);
        $project = $this->project($ws, $ws->owner, [$dev]);
        $t1 = $this->task($ws, $ws->owner, $project, [$ws->owner, $dev]);
        $t2 = $this->task($ws, $ws->owner, $project, [$dev]);

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks/{$t1->id}/time", ['started_at' => now()->subHours(5), 'minutes' => 120])->assertCreated();
        $this->actingAs($dev)->postJson("/w/{$ws->id}/tasks/{$t1->id}/time", ['started_at' => now()->subHours(5), 'minutes' => 30])->assertCreated();
        $this->actingAs($dev)->postJson("/w/{$ws->id}/tasks/{$t2->id}/time", ['started_at' => now()->subHours(2), 'minutes' => 45])->assertCreated();

        $report = $this->actingAs($ws->owner)->getJson("/w/{$ws->id}/projects/{$project->id}/time")->assertOk();

        $report->assertJsonPath('total', (120 + 30 + 45) * 60);
        $this->assertCount(2, $report->json('by_member'));
        $this->assertSame((30 + 45) * 60, collect($report->json('by_member'))->firstWhere('user.id', $dev->id)['seconds']);
    }

    public function test_people_cannot_delete_someone_elses_time(): void
    {
        $ws = $this->workspace();
        $a = $this->member($ws);
        $b = $this->member($ws);
        $task = $this->task($ws, $ws->owner, null, [$a, $b]);

        $id = $this->actingAs($a)->postJson("/w/{$ws->id}/tasks/{$task->id}/time", ['started_at' => now()->subHours(2), 'minutes' => 60])->json('id');

        $this->actingAs($b)->deleteJson("/w/{$ws->id}/tasks/{$task->id}/time/{$id}")->assertForbidden();
        $this->actingAs($a)->deleteJson("/w/{$ws->id}/tasks/{$task->id}/time/{$id}")->assertOk();
    }

    public function test_login_clocks_in_and_logout_clocks_out_and_stops_the_timer(): void
    {
        $ws = $this->workspace();
        $user = User::factory()->create(['password' => 'Correct-Horse-1']);
        $ws->addMember($user);
        $task = $this->task($ws, $ws->owner, null, [$user]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-1'])->assertRedirect();
        $presence = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($presence->ended_at);

        $this->postJson("/w/{$ws->id}/tasks/{$task->id}/timer")->assertCreated();
        $this->travel(2)->hours();
        $this->post('/logout');

        $presence->refresh();
        $this->assertSame('logout', $presence->end_reason);
        $this->assertEqualsWithDelta(7200, $presence->seconds(), 5);
        $this->assertSame(0, TimeEntry::running()->count());
    }

    public function test_people_who_leave_without_logging_out_are_clocked_out_at_their_last_activity(): void
    {
        $ws = $this->workspace();
        $user = $this->member($ws);
        $task = $this->task($ws, $ws->owner, null, [$user]);

        Presence::clockIn($user);
        $this->actingAs($user)->postJson("/w/{$ws->id}/tasks/{$task->id}/timer");

        $this->travel(40)->minutes();
        Presence::touch($user);
        $lastActivity = now()->copy();

        $this->travel(Attendance::idleMinutes() + 5)->minutes();
        $this->assertSame(1, Presence::closeStale());

        $presence = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('timeout', $presence->end_reason);
        $this->assertEqualsWithDelta($lastActivity->timestamp, $presence->ended_at->timestamp, 2);
        $this->assertEqualsWithDelta(40 * 60, TimeEntry::firstOrFail()->seconds, 3);
    }

    public function test_attendance_page_shows_only_my_own_hours_unless_i_am_an_admin(): void
    {
        $ws = $this->workspace();
        $member = $this->member($ws);
        Presence::clockIn($ws->owner);
        Presence::clockIn($member);

        $this->actingAs($member)->get("/w/{$ws->id}/attendance")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Attendance/Index')->where('isAdmin', false)->has('rows', 1)
                ->where('rows.0.user.id', $member->id)->missing('sessions.0.ip'));

        $this->actingAs($ws->owner)->get("/w/{$ws->id}/attendance")->assertOk()
            ->assertInertia(fn ($page) => $page->where('isAdmin', true)->has('rows', 2)->has('sessions.0.ip'));

        $this->actingAs($member)->get("/w/{$ws->id}/attendance?user={$ws->owner->id}")
            ->assertInertia(fn ($page) => $page->where('selected', null));

        $this->actingAs($ws->owner)->get("/w/{$ws->id}/attendance/export")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
