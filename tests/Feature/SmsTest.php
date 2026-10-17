<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\GsmModem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    /** Messages the fake modem "sent": [number, text]. Never the real device. */
    private array $sent = [];

    private bool $modemDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $test = $this;
        $this->app->bind(GsmModem::class, fn () => new class($test) extends GsmModem
        {
            public function __construct(private $test)
            {
                parent::__construct('127.0.0.1', 1);
            }

            public function send(string $number, string $text): void
            {
                $this->test->recordSend($number, $text);
            }

            public function status(): array
            {
                return ['model' => 'SIM800 R14.18', 'signal' => 23, 'registered' => true, 'operator' => 'TCI'];
            }
        });
    }

    public function recordSend(string $number, string $text): void
    {
        if ($this->modemDown) {
            throw new RuntimeException('GSM modem unreachable');
        }

        $this->sent[] = [$number, $text];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function asAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_only_site_admins_can_reach_the_sms_section(): void
    {
        $ws = $this->workspace();

        foreach (['get' => '/admin/sms', 'post' => '/admin/sms/send'] as $method => $url) {
            $this->actingAs($ws->owner)->withSession(['auth.password_confirmed_at' => time()])->{$method}($url, ['numbers' => '09121234567', 'body' => 'x'])->assertNotFound();
        }

        $this->assertSame([], $this->sent);
        $this->asAdmin($this->admin())->get('/admin/sms')->assertOk();
    }

    public function test_admin_sends_to_members_and_typed_numbers_in_international_form(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['phone' => '۰۹۱۲۱۱۱۲۲۳۳']);

        $this->asAdmin($admin)->post('/admin/sms/send', [
            'users' => [$member->id],
            'numbers' => "09351234567, +989121112233\n0021",
            'body' => 'جلسه فردا ساعت ۱۰',
        ])->assertRedirect()->assertSessionHas('success');

        // Persian digits normalised; the duplicate (same number twice) sent once; "0021" rejected.
        $this->assertEqualsCanonicalizing(['+989121112233', '+989351234567'], array_column($this->sent, 0));
        $this->assertSame(1, SmsMessage::where('status', 'invalid')->count());
        $this->assertSame(2, SmsMessage::where('status', 'sent')->count());

        $stored = SmsMessage::where('user_id', $member->id)->firstOrFail();
        $this->assertStringContainsString('***', $stored->phone);
        $this->assertSame($admin->id, $stored->sent_by);
    }

    public function test_daily_limit_is_enforced_and_failed_sends_give_the_slot_back(): void
    {
        $admin = $this->admin();
        Setting::put(['sms_daily_limit' => 2]);

        $this->modemDown = true;
        $this->asAdmin($admin)->post('/admin/sms/send', ['numbers' => '09120000001', 'body' => 'x']);
        $this->assertSame('failed', SmsMessage::latest('id')->first()->status);

        $this->modemDown = false;
        $this->asAdmin($admin)->post('/admin/sms/send', ['numbers' => '09120000001 09120000002 09120000003', 'body' => 'x']);

        $this->assertCount(2, $this->sent);
        $this->assertSame(1, SmsMessage::where('status', 'limited')->count());
    }

    public function test_log_driver_sends_nothing(): void
    {
        Setting::put(['sms_driver' => 'log']);

        $this->asAdmin($this->admin())->post('/admin/sms/send', ['numbers' => '09121234567', 'body' => 'test'])->assertSessionHas('success');

        $this->assertSame([], $this->sent);
        $this->assertSame('logged', SmsMessage::firstOrFail()->status);
    }

    public function test_connection_test_and_settings_validation(): void
    {
        $admin = $this->admin();

        $this->asAdmin($admin)->postJson('/admin/sms/test')->assertOk()->assertJson(['ok' => true, 'operator' => 'TCI', 'signal' => 23]);

        $base = ['driver' => 'gsm', 'port' => 4444, 'timeout' => 8, 'country_code' => '98', 'daily_limit' => 100];
        $this->asAdmin($admin)->put('/admin/sms/settings', [...$base, 'host' => 'http://evil/x?y'])->assertSessionHasErrors('host');
        $this->asAdmin($admin)->put('/admin/sms/settings', [...$base, 'host' => '192.168.77.248', 'daily_limit' => 999])->assertSessionHasErrors('daily_limit');
        $this->asAdmin($admin)->put('/admin/sms/settings', [...$base, 'host' => '192.168.77.250'])->assertSessionHasNoErrors();

        $this->assertSame('192.168.77.250', Setting::get('sms_host'));
    }

    public function test_the_modem_refuses_numbers_that_could_inject_at_commands(): void
    {
        $this->expectException(RuntimeException::class);

        (new GsmModem('127.0.0.1', 1))->send("+98912\"\r\nAT+CMGD=1", 'x');
    }
}
