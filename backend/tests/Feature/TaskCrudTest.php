<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    private function rep(): User
    {
        $batch = Batch::factory()->create();

        return User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
    }

    public function test_representative_can_create_task_and_ownership_is_server_derived(): void
    {
        $rep = $this->rep();
        Sanctum::actingAs($rep);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Database Assignment 2',
            'description' => 'Normalize to 3NF.',
            'type' => 'Assignment', // display casing must be accepted
            'deadline' => now()->addDays(2)->toIso8601String(),
            'created_by' => 9999, // must be ignored
            'batch_id' => 9999,   // must be ignored
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Database Assignment 2')
            ->assertJsonPath('data.type', 'assignment')
            ->assertJsonPath('data.created_by', $rep->id)
            ->assertJsonPath('data.batch_id', $rep->batch_id);
    }

    public function test_create_validates_input(): void
    {
        Sanctum::actingAs($this->rep());

        $this->postJson('/api/tasks', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type', 'deadline']);

        $this->postJson('/api/tasks', [
            'title' => 'Bad type',
            'type' => 'party',
            'deadline' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->postJson('/api/tasks', [
            'title' => 'Past deadline',
            'type' => 'quiz',
            'deadline' => now()->subDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('deadline');
    }

    public function test_student_list_is_ordered_incomplete_first_then_deadline(): void
    {
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);

        $later = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'deadline' => now()->addDays(9),
        ]);
        $overdue = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'deadline' => now()->subDay(),
        ]);
        $done = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'deadline' => now()->addHour(),
        ]);
        $done->completions()->create(['student_id' => $student->id, 'completed_at' => now()]);

        Sanctum::actingAs($student);
        $ids = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('id')->all();

        // Overdue incomplete first, then by deadline; completed sinks to the bottom.
        $this->assertSame([$overdue->id, $later->id, $done->id], $ids);
    }

    public function test_list_supports_pagination(): void
    {
        $rep = $this->rep();
        Task::factory()->count(5)->create(['batch_id' => $rep->batch_id, 'created_by' => $rep->id]);

        Sanctum::actingAs($rep);
        $response = $this->getJson('/api/tasks?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_update_ignores_ownership_fields(): void
    {
        $rep = $this->rep();
        $other = User::factory()->create();
        $task = Task::factory()->create(['batch_id' => $rep->batch_id, 'created_by' => $rep->id]);

        Sanctum::actingAs($rep);
        $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'Renamed',
            'created_by' => $other->id,
            'batch_id' => 12345,
        ])->assertOk()->assertJsonPath('data.title', 'Renamed');

        $task->refresh();
        $this->assertSame($rep->id, $task->created_by);
        $this->assertSame($rep->batch_id, $task->batch_id);
    }

    public function test_delete_deactivates_and_hides_from_students(): void
    {
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);
        $task = Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id]);

        Sanctum::actingAs($rep);
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
        $this->assertFalse($task->refresh()->is_active);

        // History row survives deactivation.
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        Sanctum::actingAs($student);
        $ids = collect($this->getJson('/api/tasks')->json('data'))->pluck('id')->all();
        $this->assertNotContains($task->id, $ids);
        $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_deadlines_are_iso8601(): void
    {
        $rep = $this->rep();
        $task = Task::factory()->create(['batch_id' => $rep->batch_id, 'created_by' => $rep->id]);

        Sanctum::actingAs($rep);
        $deadline = $this->getJson("/api/tasks/{$task->id}")->json('data.deadline');

        $this->assertNotFalse(date_create($deadline));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $deadline);
    }
}
