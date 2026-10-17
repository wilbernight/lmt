<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LetterPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_letters_print_on_the_letterhead_only_for_people_who_can_read_them(): void
    {
        $ws = $this->workspace();
        $to = $this->member($ws);
        $bystander = $this->member($ws);
        Setting::put(['company_name' => 'WilberNight', 'company_website' => 'wilbernight.net', 'company_phone' => '021-12345678']);

        $this->actingAs($ws->owner)->post("/w/{$ws->id}/letters", [
            'subject' => 'قرارداد <b>میزبانی</b>',
            'body' => "سلام\n<script>alert(1)</script>",
            'priority' => 'urgent',
            'confidential' => true,
            'to' => [$to->id],
        ]);
        $letter = $ws->letters()->firstOrFail();

        $page = $this->actingAs($to)->get("/w/{$ws->id}/letters/{$letter->id}/print")->assertOk();

        $page->assertSee('/brand/icon.png', false)
            ->assertSee('wilbernight.net')
            ->assertSee('021-12345678')
            ->assertSee('محرمانه')
            ->assertSee('فوری')
            ->assertSee('کد رهگیری')
            // User text is escaped, never rendered as HTML.
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<b>میزبانی</b>', false);

        $this->assertStringContainsString("nonce=", $page->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'letter.printed']);

        $this->actingAs($bystander)->get("/w/{$ws->id}/letters/{$letter->id}/print")->assertForbidden();
    }

    public function test_admins_edit_the_letterhead_details(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put('/admin/settings', [
            'registration_open' => true, 'default_storage_mb' => 2048, 'default_max_workspaces' => 5, 'max_upload_mb' => 20,
            'company_name' => 'شرکت نمونه', 'company_tagline' => 'میزبانی', 'company_address' => 'تهران',
            'company_phone' => '021-1234', 'company_email' => 'info@example.com', 'company_website' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('company_website');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put('/admin/settings', [
            'registration_open' => true, 'default_storage_mb' => 2048, 'default_max_workspaces' => 5, 'max_upload_mb' => 20,
            'company_name' => 'شرکت نمونه', 'company_website' => 'example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('شرکت نمونه', Setting::get('company_name'));
    }
}
