<?php

namespace Tests\Feature;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A group is a (batch year, department) PAIR, and both halves of that pair are
 * part of the boundary.
 *
 * The existing suite already proves tasks don't leak between two arbitrary
 * batches. What this file adds is the two ways a group can be "adjacent":
 * same year / different department, and same department / different year.
 * Those are the combinations a wrong scoping query would let through — e.g.
 * scoping on department alone would merge 2027 CCE with 2030 CCE.
 */
class GroupIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Batch, 1: User, 2: User, 3: Task} group, rep, student, task */
    private function group(BatchYear $batchYear, Department $department, string $taskTitle): array
    {
        $group = Batch::factory()->group($batchYear, $department)->create();
        $rep = User::factory()->create(['batch_id' => $group->id, 'role' => Role::Representative]);
        $student = User::factory()->create(['batch_id' => $group->id, 'role' => Role::Student]);
        $task = Task::factory()->create([
            'batch_id' => $group->id,
            'created_by' => $rep->id,
            'title' => $taskTitle,
        ]);

        return [$group, $rep, $student, $task];
    }

    public function test_same_batch_year_different_department_are_separate_groups(): void
    {
        [, , $cceStudent, $cceTask] = $this->group(BatchYear::Y2027, Department::CCE, '2027 CCE Assignment');
        [, , $cseStudent, $cseTask] = $this->group(BatchYear::Y2027, Department::CSE, '2027 CSE Assignment');

        $this->assertNotSame($cceStudent->batch_id, $cseStudent->batch_id);

        Sanctum::actingAs($cceStudent);
        $titles = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('title');

        $this->assertContains('2027 CCE Assignment', $titles);
        $this->assertNotContains('2027 CSE Assignment', $titles);

        // Not just filtered out of the list — unreachable by direct id too.
        $this->getJson("/api/tasks/{$cseTask->id}")->assertForbidden();
        $this->getJson("/api/tasks/{$cceTask->id}")->assertOk();
    }

    public function test_same_department_different_batch_year_are_separate_groups(): void
    {
        [, , $y2027Student] = $this->group(BatchYear::Y2027, Department::CCE, '2027 CCE Assignment');
        [, , , $y2030Task] = $this->group(BatchYear::Y2030, Department::CCE, '2030 CCE Assignment');

        Sanctum::actingAs($y2027Student);
        $titles = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('title');

        $this->assertContains('2027 CCE Assignment', $titles);
        $this->assertNotContains('2030 CCE Assignment', $titles);
        $this->getJson("/api/tasks/{$y2030Task->id}")->assertForbidden();
    }

    public function test_the_two_2028_batches_are_distinct_groups(): void
    {
        // "2028" and "2028++" are different batches that happen to share a
        // prefix — a LIKE-style or numeric-cast comparison would merge them.
        [, , $student2028] = $this->group(BatchYear::Y2028, Department::CSE, '2028 CSE Assignment');
        [, , , $task2028Plus] = $this->group(BatchYear::Y2028Plus, Department::CSE, '2028++ CSE Assignment');

        Sanctum::actingAs($student2028);
        $titles = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('title');

        $this->assertContains('2028 CSE Assignment', $titles);
        $this->assertNotContains('2028++ CSE Assignment', $titles);
        $this->getJson("/api/tasks/{$task2028Plus->id}")->assertForbidden();
    }

    public function test_a_representative_cannot_manage_a_neighbouring_departments_task(): void
    {
        [, $cceRep] = $this->group(BatchYear::Y2029, Department::CCE, '2029 CCE Assignment');
        [, , , $cseTask] = $this->group(BatchYear::Y2029, Department::CSE, '2029 CSE Assignment');

        Sanctum::actingAs($cceRep);

        $this->putJson("/api/tasks/{$cseTask->id}", [
            'title' => 'Hijacked',
            'type' => 'quiz',
            'deadline' => now()->addWeek()->toIso8601String(),
        ])->assertForbidden();

        $this->deleteJson("/api/tasks/{$cseTask->id}")->assertForbidden();
        $this->getJson("/api/tasks/{$cseTask->id}/statistics")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $cseTask->id, 'title' => '2029 CSE Assignment', 'is_active' => true]);
    }

    public function test_a_created_task_is_always_bound_to_the_creators_own_group(): void
    {
        [$cceGroup, $cceRep] = $this->group(BatchYear::Y2030, Department::CCE, 'Existing');
        $cseGroup = Batch::factory()->group(BatchYear::Y2030, Department::CSE)->create();

        Sanctum::actingAs($cceRep);

        // A client-supplied batch_id must be ignored, not honoured.
        $this->postJson('/api/tasks', [
            'title' => 'Server Derived Group',
            'type' => 'assignment',
            'deadline' => now()->addWeek()->toIso8601String(),
            'batch_id' => $cseGroup->id,
        ])->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Server Derived Group',
            'batch_id' => $cceGroup->id,
        ]);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Server Derived Group',
            'batch_id' => $cseGroup->id,
        ]);
    }

    public function test_statistics_only_count_students_of_the_tasks_own_group(): void
    {
        [$cceGroup, $cceRep, , $cceTask] = $this->group(BatchYear::Y2027, Department::CCE, 'Counted Assignment');
        User::factory()->count(3)->create(['batch_id' => $cceGroup->id, 'role' => Role::Student]);

        // A neighbouring department full of students must not inflate the total.
        $cseGroup = Batch::factory()->group(BatchYear::Y2027, Department::CSE)->create();
        User::factory()->count(10)->create(['batch_id' => $cseGroup->id, 'role' => Role::Student]);

        Sanctum::actingAs($cceRep);

        $this->getJson("/api/tasks/{$cceTask->id}/statistics")
            ->assertOk()
            ->assertJsonPath('total_students', 4);
    }
}
