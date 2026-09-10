<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function setupBatches(): array
    {
        $batchA = Batch::factory()->create();
        $batchB = Batch::factory()->create();

        $studentA = User::factory()->create(['batch_id' => $batchA->id, 'role' => Role::Student]);
        $repA = User::factory()->create(['batch_id' => $batchA->id, 'role' => Role::Representative]);
        $repB = User::factory()->create(['batch_id' => $batchB->id, 'role' => Role::Representative]);

        $taskA = Task::factory()->create(['batch_id' => $batchA->id, 'created_by' => $repA->id]);
        $taskB = Task::factory()->create(['batch_id' => $batchB->id, 'created_by' => $repB->id]);

        return compact('batchA', 'batchB', 'studentA', 'repA', 'repB', 'taskA', 'taskB');
    }

    private function taskPayload(): array
    {
        return [
            'title' => 'New Task',
            'description' => 'Do the thing.',
            'type' => 'assignment',
            'deadline' => now()->addDays(5)->toIso8601String(),
        ];
    }

    public function test_student_is_blocked_from_creating_updating_and_deleting(): void
    {
        ['studentA' => $student, 'taskA' => $task] = $this->setupBatches();
        Sanctum::actingAs($student);

        $this->postJson('/api/tasks', $this->taskPayload())->assertForbidden();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_representative_can_manage_own_batch(): void
    {
        ['repA' => $rep, 'taskA' => $task] = $this->setupBatches();
        Sanctum::actingAs($rep);

        $this->postJson('/api/tasks', $this->taskPayload())->assertCreated();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Updated'])->assertOk();
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
    }

    public function test_cross_batch_task_access_is_blocked(): void
    {
        ['studentA' => $student, 'repA' => $repA, 'taskB' => $taskB] = $this->setupBatches();

        Sanctum::actingAs($student);
        $this->getJson("/api/tasks/{$taskB->id}")->assertForbidden();
        $this->postJson("/api/tasks/{$taskB->id}/complete")->assertForbidden();

        Sanctum::actingAs($repA);
        $this->getJson("/api/tasks/{$taskB->id}")->assertForbidden();
        $this->putJson("/api/tasks/{$taskB->id}", ['title' => 'Hijack'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$taskB->id}")->assertForbidden();
        $this->getJson("/api/tasks/{$taskB->id}/statistics")->assertForbidden();
    }

    public function test_student_task_list_contains_only_own_batch(): void
    {
        ['studentA' => $student, 'taskA' => $taskA] = $this->setupBatches();
        Sanctum::actingAs($student);

        $ids = collect($this->getJson('/api/tasks')->json('data'))->pluck('id')->all();

        $this->assertContains($taskA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_student_cannot_view_statistics(): void
    {
        ['studentA' => $student, 'taskA' => $task] = $this->setupBatches();
        Sanctum::actingAs($student);

        $this->getJson("/api/tasks/{$task->id}/statistics")->assertForbidden();
    }
}
