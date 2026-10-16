<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
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

    public function test_files_are_stored_privately_under_random_names(): void
    {
        $ws = $this->workspace();

        $response = $this->actingAs($ws->owner)->post("/w/{$ws->id}/attachments", [
            'file' => UploadedFile::fake()->image('../../evil.png', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $attachment = Attachment::findOrFail($response->json('id'));
        $this->assertStringStartsWith("attachments/{$ws->id}/", $attachment->path);
        $this->assertStringEndsWith('.bin', $attachment->path);
        $this->assertStringNotContainsString('/', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertGreaterThan(0, $ws->fresh()->storage_used);
    }

    public function test_dangerous_file_types_are_rejected(): void
    {
        $ws = $this->workspace();

        foreach (['shell.php', 'page.html', 'icon.svg', 'run.exe'] as $name) {
            $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/attachments", [
                'file' => UploadedFile::fake()->create($name, 10),
            ])->assertUnprocessable();
        }

        // A PHP file renamed to .png is caught by content sniffing.
        $this->actingAs($ws->owner)->postJson("/w/{$ws->id}/attachments", [
            'file' => $this->realFile('photo.png', '<?php system($_GET["c"]); ?>'),
        ])->assertUnprocessable();
    }

    public function test_attachments_follow_the_permissions_of_their_task(): void
    {
        $ws = $this->workspace();
        $outsider = $this->member($ws);
        $project = $this->project($ws, $ws->owner);
        $task = $this->task($ws, $ws->owner, $project);

        $id = $this->actingAs($ws->owner)->post("/w/{$ws->id}/attachments", [
            'file' => $this->realFile('report.pdf', "%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF"),
        ], ['Accept' => 'application/json'])->json('id');

        $this->actingAs($ws->owner)->patchJson("/w/{$ws->id}/tasks/{$task->id}", ['attachments' => [$id]])->assertOk();

        $this->actingAs($ws->owner)->get("/attachments/{$id}")->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/octet-stream');

        $this->actingAs($outsider)->get("/attachments/{$id}")->assertForbidden();
    }

    public function test_someone_else_cannot_claim_my_unattached_upload(): void
    {
        $ws = $this->workspace();
        $other = $this->member($ws);

        $id = $this->actingAs($ws->owner)->post("/w/{$ws->id}/attachments", [
            'file' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->json('id');

        $this->actingAs($other)->postJson("/w/{$ws->id}/tasks", ['title' => 'x', 'attachments' => [$id]])->assertCreated();

        $this->assertNull(Attachment::find($id)->attachable_id);
        $this->actingAs($other)->get("/attachments/{$id}")->assertForbidden();
    }
}
