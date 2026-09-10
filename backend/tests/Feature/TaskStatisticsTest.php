<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function setupBatch(int $students = 4): array
    {
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $studentIds = User::factory()->count($students)
            ->create(['batch_id' => $batch->id, 'role' => Role::Student])
            ->pluck('id')
            ->all();
        $task = Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id]);

        return compact('batch', 'rep', 'studentIds', 'task');
    }

    private function complete(Task $task, array $studentIds): void
    {
        foreach ($studentIds as $id) {
            $task->completions()->create(['student_id' => $id, 'completed_at' => now()]);
        }
    }

    public function test_statistics_with_zero_completions(): void
    {
        ['rep' => $rep, 'task' => $task] = $this->setupBatch();
        Sanctum::actingAs($rep);

        $this->getJson("/api/tasks/{$task->id}/statistics")->assertOk()
            ->assertJson([
                'task_id' => $task->id,
                'task_title' => $task->title,
                'total_students' => 4,
                'completed_students' => 0,
                'remaining_students' => 4,
                'completion_percentage' => 0,
            ]);
    }

    public function test_statistics_with_partial_completion(): void
    {
        ['rep' => $rep, 'task' => $task, 'studentIds' => $ids] = $this->setupBatch();
        $this->complete($task, array_slice($ids, 0, 1));
        Sanctum::actingAs($rep);

        $this->getJson("/api/tasks/{$task->id}/statistics")->assertOk()
            ->assertJson([
                'total_students' => 4,
                'completed_students' => 1,
                'remaining_students' => 3,
                'completion_percentage' => 25,
            ]);
    }

    public function test_statistics_with_full_completion(): void
    {
        ['rep' => $rep, 'task' => $task, 'studentIds' => $ids] = $this->setupBatch(50);
        $this->complete($task, $ids);
        Sanctum::actingAs($rep);

        $this->getJson("/api/tasks/{$task->id}/statistics")->assertOk()
            ->assertJson([
                'total_students' => 50,
                'completed_students' => 50,
                'remaining_students' => 0,
                'completion_percentage' => 100,
            ]);
    }

    public function test_statistics_with_empty_batch_has_no_division_error(): void
    {
        $batch = Batch::factory()->create();
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);
        $task = Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id]);
        Sanctum::actingAs($rep);

        $this->getJson("/api/tasks/{$task->id}/statistics")->assertOk()
            ->assertJson([
                'total_students' => 0,
                'completed_students' => 0,
                'remaining_students' => 0,
                'completion_percentage' => 0,
            ]);
    }

    public function test_representative_list_includes_statistics_without_n_plus_one(): void
    {
        ['rep' => $rep, 'task' => $task, 'studentIds' => $ids] = $this->setupBatch();
        $this->complete($task, array_slice($ids, 0, 2));

        Sanctum::actingAs($rep);
        $response = $this->getJson('/api/tasks')->assertOk();

        $response->assertJsonPath('data.0.statistics.completed_students', 2);
        $response->assertJsonPath('data.0.statistics.total_students', 4);
        $response->assertJsonPath('data.0.statistics.completion_percentage', 50);
    }
}
