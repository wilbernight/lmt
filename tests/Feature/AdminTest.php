<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /** Admin pages re-ask for the password; simulate a fresh confirmation. */
    private function asAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_the_admin_panel_is_invisible_to_regular_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/admin')->assertNotFound();
        $this->actingAs($user)->put("/admin/users/{$user->id}", ['is_admin' => true, 'can_create_workspaces' => true])->assertNotFound();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_admin_pages_require_password_confirmation(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertRedirect('/user/confirm-password');
    }

    public function test_admin_can_open_every_panel_page(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->workspace($user);

        foreach (['/admin', '/admin/users', "/admin/users/{$user->id}", '/admin/workspaces', '/admin/settings'] as $path) {
            $this->asAdmin($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_can_be_required_to_use_two_factor(): void
    {
        config(['daftar.admin_requires_2fa' => true]);

        $this->asAdmin($this->admin())->get('/admin')->assertRedirect(route('settings.security'));
    }

    public function test_is_admin_cannot_be_set_through_the_profile_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/user/profile-information', ['name' => 'X Y', 'email' => $user->email, 'is_admin' => true]);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_suspending_a_user_ends_sessions_and_blocks_login(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['password' => 'Correct-Horse-1']);

        $this->asAdmin($admin)->post("/admin/users/{$user->id}/suspend", ['reason' => 'تخلف'])->assertRedirect();
        $this->assertNotNull($user->fresh()->suspended_at);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->asAdmin($admin)->post("/admin/users/{$user->id}/unsuspend");
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-1'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_admins_cannot_suspend_or_demote_themselves(): void
    {
        $admin = $this->admin();

        $this->asAdmin($admin)->post("/admin/users/{$admin->id}/suspend")->assertStatus(422);
        $this->asAdmin($admin)->put("/admin/users/{$admin->id}", ['is_admin' => false, 'can_create_workspaces' => true])->assertSessionHasErrors('is_admin');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_workspace_count_limit_is_enforced(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->workspace($user);

        $this->asAdmin($admin)->put("/admin/users/{$user->id}", ['is_admin' => false, 'can_create_workspaces' => true, 'max_workspaces' => 1])->assertRedirect();

        $this->actingAs($user)->post('/workspaces', ['name' => 'دومی'])->assertSessionHasErrors('name');
        $this->assertSame(1, $user->ownedWorkspaces()->count());

        $this->asAdmin($admin)->put("/admin/users/{$user->id}", ['is_admin' => false, 'can_create_workspaces' => true, 'max_workspaces' => 2]);
        $this->actingAs($user)->post('/workspaces', ['name' => 'دومی'])->assertRedirect();
        $this->assertSame(2, $user->ownedWorkspaces()->count());
    }

    public function test_storage_quota_changes_are_enforced_on_upload(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $ws = $this->workspace();

        $this->asAdmin($admin)->put("/admin/workspaces/{$ws->id}", ['storage_limit_mb' => 10])->assertRedirect();
        $this->assertSame(10 * 1024 * 1024, $ws->fresh()->storage_limit);

        $ws->forceFill(['storage_used' => 10 * 1024 * 1024 - 100])->save();

        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/attachments", [
            'file' => UploadedFile::fake()->image('big.png', 400, 400),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_user_quota_applies_to_new_and_existing_workspaces(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $existing = $this->workspace($user);

        $this->asAdmin($admin)->put("/admin/users/{$user->id}", [
            'is_admin' => false, 'can_create_workspaces' => true, 'storage_quota_mb' => 500, 'apply_storage_to_existing' => true,
        ])->assertRedirect();

        $this->assertSame(500 * 1024 * 1024, $existing->fresh()->storage_limit);
        $this->assertSame(500 * 1024 * 1024, $this->workspace($user->fresh())->storage_limit);
    }

    public function test_a_locked_workspace_is_closed_to_its_members(): void
    {
        $admin = $this->admin();
        $ws = $this->workspace();

        $this->asAdmin($admin)->post("/admin/workspaces/{$ws->id}/lock")->assertRedirect();

        $this->actingAs($ws->owner)->get("/w/{$ws->id}")->assertStatus(423);
    }

    public function test_admin_sets_a_job_title_that_appears_on_printed_letters(): void
    {
        $admin = $this->admin();
        $ws = $this->workspace();
        $member = $this->member($ws);

        $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()])
            ->put("/admin/users/{$member->id}/workspaces/{$ws->id}/job-title", ['job_title' => 'مدیرعامل'])->assertNotFound();

        $this->asAdmin($admin)->put("/admin/users/{$member->id}/workspaces/{$ws->id}/job-title", ['job_title' => 'کارشناس پشتیبانی'])->assertRedirect();
        $this->assertSame('کارشناس پشتیبانی', $ws->members()->whereKey($member->id)->first()->pivot->job_title);

        // Not a member of this workspace: nothing to edit.
        $other = $this->workspace();
        $this->asAdmin($admin)->put("/admin/users/{$member->id}/workspaces/{$other->id}/job-title", ['job_title' => 'x'])->assertNotFound();

        $this->actingAs($ws->owner)->post("/w/{$ws->id}/letters", ['subject' => 'تست', 'body' => 'متن', 'priority' => 'normal', 'to' => [$member->id]]);
        $letter = $ws->letters()->firstOrFail();
        $this->actingAs($member)->get("/w/{$ws->id}/letters/{$letter->id}/print")->assertOk()->assertSee('کارشناس پشتیبانی');
    }

    public function test_registration_can_be_closed(): void
    {
        Setting::put(['registration_open' => false]);

        $this->post('/register', [
            'name' => 'کاربر', 'email' => 'new@example.com', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!', 'terms' => true,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
