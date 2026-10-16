<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_strict_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $workspace = $this->workspace();

        $this->get("/w/{$workspace->id}")->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_users_cannot_enter_workspaces(): void
    {
        $user = User::factory()->unverified()->create();
        $workspace = $this->workspace($user);

        $this->actingAs($user)->get("/w/{$workspace->id}")->assertRedirect('/email/verify');
    }

    public function test_non_members_cannot_tell_a_foreign_workspace_from_a_missing_one(): void
    {
        $workspace = $this->workspace();
        $outsider = User::factory()->create();
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';

        // Page visits go back to the dashboard, identically for both cases.
        $this->actingAs($outsider)->get("/w/{$workspace->id}")->assertRedirect('/dashboard')->assertSessionHas('error');
        $this->actingAs($outsider)->get("/w/{$missing}/tasks")->assertRedirect('/dashboard')->assertSessionHas('error');

        // API calls and writes get a plain 404.
        $this->actingAs($outsider)->getJson("/w/{$workspace->id}/search?q=test")->assertNotFound();
        $this->actingAs($outsider)->getJson("/w/{$missing}/search?q=test")->assertNotFound();
        $this->actingAs($outsider)->post("/w/{$workspace->id}/projects", ['name' => 'x'])->assertNotFound();
    }

    public function test_password_reset_does_not_reveal_whether_an_account_exists(): void
    {
        User::factory()->create(['email' => 'real@example.com']);

        $real = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'real@example.com']);
        $fake = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        $real->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $fake->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $this->assertSame(session('status'), $real->getSession()->get('status'));
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_registration_creates_a_personal_workspace_and_rejects_weak_passwords(): void
    {
        $this->post('/register', [
            'name' => 'کاربر', 'email' => 'weak@example.com', 'password' => '123', 'password_confirmation' => '123', 'terms' => true,
        ])->assertSessionHasErrors('password');

        $this->post('/register', [
            'name' => 'کاربر جدید', 'email' => 'New@Example.com', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!', 'terms' => true,
        ])->assertRedirect();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(WorkspaceRole::Owner, $user->roleIn($user->workspaces()->first()));
    }

    public function test_suspended_accounts_are_signed_out(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $user->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
