<?php

namespace Tests\Feature;

use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmsTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{0: string, 1: string}> */
    public array $outbox = [];

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $this->app->instance(SmsSender::class, new class($test) extends SmsSender
        {
            public function __construct(private $test) {}

            public function deliver(string $phone, string $text): array
            {
                $this->test->outbox[] = [$phone, $text];

                return ['status' => 'sent', 'error' => null, 'ms' => 5];
            }
        });
    }

    private function lastCode(): string
    {
        preg_match('/\d{6}/', end($this->outbox)[1], $m);

        return $m[0];
    }

    private function smsUser(array $attributes = []): User
    {
        $user = User::factory()->create(['password' => 'Secret-pass-123']);
        $user->forceFill(['phone' => '09121234567', 'sms_two_factor_at' => now()] + $attributes)->save();

        return $user;
    }

    private function confirmPassword(): void
    {
        session(['auth.password_confirmed_at' => time()]);
    }

    public function test_login_needs_the_sms_code_after_the_password(): void
    {
        $user = $this->smsUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertCount(1, $this->outbox);
        $this->assertSame('09121234567', $this->outbox[0][0]);

        $this->get('/two-factor-challenge')->assertInertia(fn ($page) => $page->where('method', 'sms')->where('phone', '+98912***4567'));

        $this->post('/two-factor-challenge/sms', ['code' => '000000' === $this->lastCode() ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge/sms', ['code' => $this->lastCode()])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_works_once_and_dies_after_five_wrong_guesses(): void
    {
        $user = $this->smsUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-123']);
        $code = $this->lastCode();
        $wrong = $code === '123456' ? '654321' : '123456';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge/sms', ['code' => $wrong]);
        }

        // Past the per-minute throttle, still inside the code's three minutes: the code is gone anyway.
        $this->travel(61)->seconds();

        $this->post('/two-factor-challenge/sms', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_code_is_masked_in_the_sms_history_and_not_stored_in_plain_text(): void
    {
        $user = $this->smsUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-123']);
        $code = $this->lastCode();

        $this->assertStringNotContainsString($code, SmsMessage::firstOrFail()->body);
        $this->assertStringContainsString('******', SmsMessage::firstOrFail()->body);
        foreach (DB::table('cache')->pluck('value') as $value) {
            $this->assertStringNotContainsString($code, (string) $value);
        }
    }

    public function test_resend_waits_sixty_seconds(): void
    {
        $user = $this->smsUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-123']);

        $this->post('/two-factor-challenge/sms/resend')->assertSessionHas('error');
        $this->assertCount(1, $this->outbox);

        $this->travel(61)->seconds();
        $this->post('/two-factor-challenge/sms/resend')->assertSessionHas('success');
        $this->assertCount(2, $this->outbox);
    }

    public function test_the_sms_endpoint_cannot_be_used_without_a_password_step(): void
    {
        $this->smsUser();

        $this->post('/two-factor-challenge/sms', ['code' => '123456'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_recovery_codes_work_instead_of_the_sms(): void
    {
        $user = $this->smsUser();
        app(\Laravel\Fortify\Actions\GenerateNewRecoveryCodes::class)($user);
        $recovery = $user->fresh()->recoveryCodes()[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-pass-123']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_enabling_proves_the_phone_with_a_code_and_gives_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->confirmPassword();

        $this->post(route('settings.two-factor.sms.code'), ['phone' => '۰۹۳۵۱۱۱۲۲۳۳'])->assertSessionHasNoErrors();
        $this->post(route('settings.two-factor.sms.enable'), ['code' => '999999' === $this->lastCode() ? '000000' : '999999'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->usesSmsTwoFactor());

        $this->post(route('settings.two-factor.sms.enable'), ['code' => $this->lastCode()])->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertTrue($fresh->usesSmsTwoFactor());
        $this->assertSame('۰۹۳۵۱۱۱۲۲۳۳', $fresh->phone);
        $this->assertCount(8, $this->getJson(route('settings.two-factor.recovery-codes'))->json());
    }

    public function test_the_login_phone_cannot_be_changed_from_the_profile_form(): void
    {
        $user = $this->smsUser();

        $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => $user->email, 'phone' => '09350000000'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'phone');
        $this->assertSame('09121234567', $user->fresh()->phone);
    }

    public function test_sms_two_factor_satisfies_the_admin_panel_requirement(): void
    {
        config(['daftar.admin_requires_2fa' => true]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->confirmPassword();

        $this->get('/admin')->assertRedirect(route('settings.security'));

        $admin->forceFill(['phone' => '09121234567', 'sms_two_factor_at' => now()])->save();
        $this->actingAs($admin->fresh())->get('/admin')->assertOk();
    }
}
