<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Task;
use App\Support\DataCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    /** What someone holding only a database dump would see for one column. */
    private function raw(string $table, string $column, string $id): ?string
    {
        return DB::table($table)->where('id', $id)->value($column);
    }

    public function test_business_data_is_unreadable_in_the_database_but_readable_in_the_app(): void
    {
        $ws = $this->workspace();
        $member = $this->member($ws);
        $owner = $ws->owner;

        $this->actingAs($owner)->postJson("/w/{$ws->id}/tasks", [
            'title' => 'قرارداد محرمانه بانک', 'description' => 'مبلغ ۵ میلیارد', 'checklist' => ['امضای مدیرعامل'], 'assignees' => [$member->id],
        ])->assertCreated();
        $task = Task::firstOrFail();

        $this->actingAs($owner)->post("/w/{$ws->id}/customers", [
            'kind' => 'person', 'name' => 'علی رضایی', 'mobile' => '09121234567', 'email' => 'ali@bank.ir', 'stage' => 'lead', 'deal_value' => 5000000,
        ]);
        $customer = Customer::firstOrFail();

        $this->actingAs($owner)->post("/w/{$ws->id}/letters", ['subject' => 'موضوع سری', 'body' => 'متن نامه سری', 'priority' => 'normal', 'to' => [$member->id]]);
        $letter = $ws->letters()->firstOrFail();

        $checks = [
            ['tasks', 'title', $task->id, 'قرارداد'],
            ['tasks', 'description', $task->id, 'میلیارد'],
            ['checklist_items', 'body', $task->checklist()->first()->id, 'امضا'],
            ['customers', 'name', $customer->id, 'رضایی'],
            ['customers', 'mobile', $customer->id, '0912'],
            ['customers', 'email', $customer->id, 'bank.ir'],
            ['customers', 'deal_value', $customer->id, '5000000'],
            ['letters', 'subject', $letter->id, 'سری'],
            ['letters', 'body', $letter->id, 'سری'],
            ['workspaces', 'name', $ws->id, 'تیم'],
        ];

        foreach ($checks as [$table, $column, $id, $secret]) {
            $stored = $this->raw($table, $column, $id);
            $this->assertNotNull($stored, "{$table}.{$column} is stored");
            $this->assertStringNotContainsString($secret, $stored, "{$table}.{$column} must not be readable in the database");
            $this->assertStringStartsWith('eyJ', $stored, "{$table}.{$column} is ciphertext");
        }

        // Notifications (task assigned) carry the title too: encrypted as well.
        foreach (DB::table('notifications')->pluck('data') as $data) {
            $this->assertStringNotContainsString('قرارداد', (string) $data);
            $this->assertStringNotContainsString('سری', (string) $data);
        }
        $assigned = $member->notifications()->get()->firstWhere('data.kind', 'task.assigned');
        $this->assertStringContainsString('قرارداد', $assigned->data['title']);

        // The app reads everything back.
        $this->assertSame('قرارداد محرمانه بانک', $task->fresh()->title);
        $this->assertSame('09121234567', $customer->fresh()->mobile);
    }

    public function test_the_same_text_encrypts_differently_each_time_and_is_tamper_evident(): void
    {
        $cipher = app(DataCipher::class);

        $a = $cipher->encryptString('یکسان');
        $b = $cipher->encryptString('یکسان');
        $this->assertNotSame($a, $b, 'a random nonce per value: equal texts cannot be spotted in a dump');

        $payload = json_decode(base64_decode($a), true);
        $this->assertNotEmpty($payload['tag'] ?? null, 'AES-GCM authentication tag present');

        $payload['value'] = base64_encode(strrev(base64_decode($payload['value'])));
        $this->expectException(DecryptException::class);
        $cipher->decryptString(base64_encode(json_encode($payload)));
    }

    public function test_the_app_key_alone_cannot_decrypt_business_data(): void
    {
        $ws = $this->workspace();
        $stored = $this->raw('workspaces', 'name', $ws->id);

        $this->expectException(DecryptException::class);
        Crypt::decryptString($stored);
    }

    public function test_search_and_filters_still_work_on_encrypted_text(): void
    {
        $ws = $this->workspace();
        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks", ['title' => 'طراحی لوگوی جدید'])->assertCreated();
        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/tasks", ['title' => 'خرید سرور'])->assertCreated();
        $this->actingAs($ws->owner)->post("/w/{$ws->id}/customers", ['kind' => 'person', 'name' => 'شرکت آفتاب', 'mobile' => '09350001122', 'stage' => 'lead']);

        $this->actingAs($ws->owner)->getJson("/w/{$ws->id}/search?q=لوگو")
            ->assertJsonFragment(['title' => 'طراحی لوگوی جدید'])->assertJsonMissing(['title' => 'خرید سرور']);

        // Persian digits find a number stored with Latin digits.
        $this->actingAs($ws->owner)->getJson("/w/{$ws->id}/search?q=".urlencode('۰۹۳۵۰۰'))->assertJsonFragment(['title' => 'شرکت آفتاب']);

        $this->actingAs($ws->owner)->get("/w/{$ws->id}/tasks?tab=created&q=سرور")
            ->assertInertia(fn ($page) => $page->has('tasks', 1)->where('tasks.0.title', 'خرید سرور'));

        $this->actingAs($ws->owner)->get("/w/{$ws->id}/customers?q=آفتاب")
            ->assertInertia(fn ($page) => $page->where('customers.total', 1)->where('pipeline.0.total', 1));
    }

    public function test_label_names_stay_unique_even_though_they_are_encrypted(): void
    {
        $ws = $this->workspace();

        $this->actingAs($ws->owner)->post("/w/{$ws->id}/labels", ['scope' => 'task', 'name' => 'فوری', 'color' => 'rose'])->assertSessionHasNoErrors();
        $this->actingAs($ws->owner)->post("/w/{$ws->id}/labels", ['scope' => 'task', 'name' => 'فوری', 'color' => 'sky'])->assertSessionHasErrors('name');
        $this->actingAs($ws->owner)->post("/w/{$ws->id}/labels", ['scope' => 'letter', 'name' => 'فوری', 'color' => 'sky'])->assertSessionHasNoErrors();
    }

    public function test_uploaded_files_are_encrypted_on_disk(): void
    {
        Storage::fake('local');
        $ws = $this->workspace();
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, "%PDF-1.4\nsecret balance sheet\n%%EOF");

        $id = $this->actingAs($ws->owner)->post("/w/{$ws->id}/attachments", [
            'file' => new UploadedFile($path, 'ترازنامه.pdf', null, null, true),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $attachment = Attachment::findOrFail($id);
        $onDisk = Storage::disk('local')->get($attachment->path);

        $this->assertStringStartsWith('WNE1', $onDisk);
        $this->assertStringNotContainsString('balance sheet', $onDisk);
        $this->assertStringNotContainsString('ترازنامه', (string) $this->raw('attachments', 'original_name', $id));

        $this->actingAs($ws->owner)->get("/attachments/{$id}?download=1")->assertOk()
            ->assertSee('secret balance sheet', false);
    }
}
