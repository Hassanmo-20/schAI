<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(): array
    {
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);
        $task = Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id]);

        return compact('batch', 'rep', 'student', 'task');
    }

    public function test_student_can_complete_and_uncomplete(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        Sanctum::actingAs($student);

        $this->postJson("/api/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('is_completed', true);

        $this->assertDatabaseHas('task_completions', [
            'task_id' => $task->id,
            'student_id' => $student->id,
        ]);

        // Completion is reflected in the task payload.
        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->deleteJson("/api/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('is_completed', false);

        $this->assertDatabaseMissing('task_completions', [
            'task_id' => $task->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_duplicate_completion_returns_conflict(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        Sanctum::actingAs($student);

        $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();
        $this->postJson("/api/tasks/{$task->id}/complete")->assertConflict();

        $this->assertSame(1, $task->completions()->count());
    }

    public function test_completion_conflict_is_handled_even_when_row_appears_mid_request(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        Sanctum::actingAs($student);

        // Simulates the race window: the row exists at INSERT time without any
        // prior SELECT having seen it. Must surface as 409, never a 500.
        TaskCompletion::insert([
            'task_id' => $task->id,
            'student_id' => $student->id,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/tasks/{$task->id}/complete")
            ->assertConflict()
            ->assertJsonPath('message', 'Task already marked as complete');
    }

    public function test_uncompleting_without_completion_returns_not_found(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        Sanctum::actingAs($student);

        $this->deleteJson("/api/tasks/{$task->id}/complete")->assertNotFound();
    }

    public function test_completion_is_always_recorded_for_the_authenticated_student(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        $other = User::factory()->create(['batch_id' => $student->batch_id, 'role' => Role::Student]);
        Sanctum::actingAs($student);

        // There is no student_id parameter to abuse — the token decides.
        $this->postJson("/api/tasks/{$task->id}/complete", ['student_id' => $other->id])->assertOk();

        $this->assertDatabaseHas('task_completions', ['task_id' => $task->id, 'student_id' => $student->id]);
        $this->assertDatabaseMissing('task_completions', ['task_id' => $task->id, 'student_id' => $other->id]);
    }

    public function test_inactive_task_cannot_be_completed(): void
    {
        ['student' => $student, 'task' => $task] = $this->makeFixture();
        $task->update(['is_active' => false]);
        Sanctum::actingAs($student);

        $this->postJson("/api/tasks/{$task->id}/complete")->assertForbidden();
    }
}
