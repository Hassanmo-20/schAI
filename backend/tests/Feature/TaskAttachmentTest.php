<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(): array
    {
        Storage::fake(config('filesystems.default'));
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);

        return compact('batch', 'rep', 'student');
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Task with files',
            'description' => 'See attachments.',
            'type' => 'assignment',
            'deadline' => now()->addDays(3)->toIso8601String(),
        ], $extra);
    }

    /** Real 1x1 PNG bytes — no GD extension required. */
    private function realPng(string $name = 'diagram.png'): UploadedFile
    {
        $path = sys_get_temp_dir().'/schai-'.uniqid().'.png';
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /** Minimal real PDF so finfo detects application/pdf. */
    private function realPdf(string $name = 'rubric.pdf', int $padBytes = 0): UploadedFile
    {
        $path = sys_get_temp_dir().'/schai-'.uniqid().'.pdf';
        $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        if ($padBytes > 0) {
            $content .= str_repeat('0', $padBytes);
        }
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    public function test_representative_can_upload_image_and_pdf(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        Sanctum::actingAs($rep);

        // post() (not postJson) so files travel as multipart/form-data.
        $response = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPng(), $this->realPdf()],
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data.attachments');
        $this->assertDatabaseCount('task_attachments', 2);

        // Stored under a hashed path — the client filename is metadata only.
        $path = TaskAttachment::first()->file_path;
        $this->assertStringNotContainsString('diagram.png', $path);
        Storage::disk(config('filesystems.default'))->assertExists($path);
    }

    public function test_invalid_extension_is_rejected(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        Sanctum::actingAs($rep);

        $evil = $this->realPng('shell.exe');

        $this->post('/api/tasks', $this->payload(['attachments' => [$evil]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_oversized_file_is_rejected(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        Sanctum::actingAs($rep);

        // Limit is 5120 KB; this file is ~6 MB.
        $huge = $this->realPdf('huge.pdf', 6 * 1024 * 1024);

        $this->post('/api/tasks', $this->payload(['attachments' => [$huge]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_student_can_download_own_batch_attachment(): void
    {
        ['rep' => $rep, 'student' => $student] = $this->makeFixture();
        Sanctum::actingAs($rep);

        $taskId = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPdf('notes.pdf')],
        ]), ['Accept' => 'application/json'])->json('data.id');
        $attachmentId = $this->getJson("/api/tasks/{$taskId}")->json('data.attachments.0.id');

        Sanctum::actingAs($student);
        $this->get("/api/tasks/{$taskId}/attachments/{$attachmentId}")->assertOk();
    }

    public function test_representative_can_delete_an_attachment_and_its_file(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        Sanctum::actingAs($rep);

        $taskId = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPdf('notes.pdf')],
        ]), ['Accept' => 'application/json'])->json('data.id');

        $attachment = TaskAttachment::first();
        $path = $attachment->file_path;

        $this->deleteJson("/api/tasks/{$taskId}/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Attachment deleted');

        $this->assertDatabaseCount('task_attachments', 0);
        Storage::disk(config('filesystems.default'))->assertMissing($path);
    }

    public function test_student_cannot_delete_an_attachment(): void
    {
        ['rep' => $rep, 'student' => $student] = $this->makeFixture();
        Sanctum::actingAs($rep);

        $taskId = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPdf('notes.pdf')],
        ]), ['Accept' => 'application/json'])->json('data.id');
        $attachmentId = TaskAttachment::first()->id;

        Sanctum::actingAs($student);
        $this->deleteJson("/api/tasks/{$taskId}/attachments/{$attachmentId}")->assertForbidden();
        $this->assertDatabaseCount('task_attachments', 1);
    }

    public function test_attachment_from_another_task_is_not_reachable(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        Sanctum::actingAs($rep);

        $taskA = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPdf('a.pdf')],
        ]), ['Accept' => 'application/json'])->json('data.id');
        $taskB = $this->post('/api/tasks', $this->payload(), ['Accept' => 'application/json'])->json('data.id');

        $attachmentOfA = TaskAttachment::first()->id;

        // IDOR guard: attachment id is valid, but it does not belong to task B.
        $this->get("/api/tasks/{$taskB}/attachments/{$attachmentOfA}")->assertNotFound();
        $this->deleteJson("/api/tasks/{$taskB}/attachments/{$attachmentOfA}")->assertNotFound();
    }

    public function test_cross_batch_download_is_forbidden(): void
    {
        ['rep' => $rep] = $this->makeFixture();
        $otherBatch = Batch::factory()->create();
        $outsider = User::factory()->create(['batch_id' => $otherBatch->id, 'role' => Role::Student]);

        Sanctum::actingAs($rep);
        $taskId = $this->post('/api/tasks', $this->payload([
            'attachments' => [$this->realPdf('notes.pdf')],
        ]), ['Accept' => 'application/json'])->json('data.id');
        $attachmentId = $this->getJson("/api/tasks/{$taskId}")->json('data.attachments.0.id');

        Sanctum::actingAs($outsider);
        $this->get("/api/tasks/{$taskId}/attachments/{$attachmentId}")->assertForbidden();
    }
}
