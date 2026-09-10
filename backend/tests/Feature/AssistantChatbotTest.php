<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the deterministic chatbot commands (add task, complete task,
 * deadline queries, general fallback). These never call OpenAI — the tests
 * run with no provider key configured at all to prove that explicitly.
 */
class AssistantChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No key at all: proves add/complete/query never depend on OpenAI.
        config()->set('services.openai.key', null);
        Http::preventStrayRequests();
    }

    private function studentAndRep(): array
    {
        $batch = Batch::factory()->create();
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);
        $rep = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Representative]);

        return [$student, $rep, $batch];
    }

    // -------------------------------------------------------------- add task

    public function test_student_can_add_a_task_via_chat_and_it_is_really_stored(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'I have a Database assignment on Thursday',
        ])->assertOk();

        $message = $response->json('data.message');
        $this->assertStringContainsStringIgnoringCase('database assignment', $message);
        $this->assertTrue($response->json('data.taskChanged'));

        // The task must actually exist — not just be claimed in the chat text.
        $this->assertDatabaseHas('tasks', [
            'title' => 'Database Assignment',
            'type' => 'assignment',
            'batch_id' => $student->batch_id,
            'created_by' => $student->id,
        ]);

        // And it must appear through the real task listing endpoint too.
        $listed = $this->getJson('/api/tasks?per_page=50')->assertOk()->json('data');
        $this->assertTrue(collect($listed)->contains(fn ($t) => $t['title'] === 'Database Assignment'));
    }

    public function test_add_task_infers_type_from_keyword(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $this->postJson('/api/assistant/chat', ['message' => 'I have a quiz tomorrow'])->assertOk();

        $this->assertDatabaseHas('tasks', ['title' => 'Quiz', 'type' => 'quiz']);
    }

    public function test_add_task_supports_next_weekday_phrasing(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $this->postJson('/api/assistant/chat', ['message' => 'Add a project due next Monday'])->assertOk();

        $this->assertDatabaseHas('tasks', ['title' => 'Project', 'type' => 'project']);
    }

    public function test_add_task_asks_for_clarification_when_date_is_missing(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'Add a Database assignment',
        ])->assertOk();

        $this->assertFalse($response->json('data.taskChanged'));
        $this->assertStringContainsString('when', mb_strtolower($response->json('data.message')));
        $this->assertDatabaseMissing('tasks', ['title' => 'Database Assignment']);
    }

    public function test_representative_can_also_add_a_task_via_chat(): void
    {
        [, $rep] = $this->studentAndRep();
        Sanctum::actingAs($rep);

        $this->postJson('/api/assistant/chat', ['message' => 'Add a Database assignment for Thursday'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', ['title' => 'Database Assignment', 'created_by' => $rep->id]);
    }

    public function test_user_without_a_batch_cannot_add_a_task(): void
    {
        $user = User::factory()->create(['batch_id' => null, 'role' => Role::Student]);
        Sanctum::actingAs($user);

        $this->postJson('/api/assistant/chat', ['message' => 'Add a quiz for Friday'])->assertOk();

        $this->assertDatabaseCount('tasks', 0);
    }

    // --------------------------------------------------------- complete task

    public function test_student_can_complete_a_task_via_chat_and_it_is_really_stored(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        $task = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'Database Assignment 2',
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'I finished the Database assignment',
        ])->assertOk();

        $this->assertTrue($response->json('data.taskChanged'));
        $this->assertDatabaseHas('task_completions', ['task_id' => $task->id, 'student_id' => $student->id]);

        // Reflected through the real task endpoint too.
        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.is_completed', true);
    }

    public function test_complete_task_handles_already_completed(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        $task = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'OOP Quiz',
        ]);
        $task->completions()->create(['student_id' => $student->id, 'completed_at' => now()]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'I completed OOP Quiz'])->assertOk();

        $this->assertFalse($response->json('data.taskChanged'));
        $this->assertStringContainsString('already', mb_strtolower($response->json('data.message')));
        $this->assertSame(1, TaskCompletion::where('task_id', $task->id)->count());
    }

    public function test_complete_task_reports_not_found_instead_of_guessing(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'I finished the Quantum Mechanics assignment',
        ])->assertOk();

        $this->assertStringContainsString("couldn't find", mb_strtolower($response->json('data.message')));
        $this->assertDatabaseCount('task_completions', 0);
    }

    public function test_complete_task_asks_for_clarification_on_ambiguous_match(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'Database Assignment 1']);
        Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'Database Assignment 2']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'I finished the Database assignment',
        ])->assertOk();

        $this->assertStringContainsString('which one', mb_strtolower($response->json('data.message')));
        $this->assertDatabaseCount('task_completions', 0);
    }

    public function test_representative_cannot_complete_a_task_via_chat(): void
    {
        [, $rep, $batch] = $this->studentAndRep();
        $task = Task::factory()->create(['batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'OOP Quiz']);
        Sanctum::actingAs($rep);

        $this->postJson('/api/assistant/chat', ['message' => 'I finished OOP Quiz'])->assertOk();

        $this->assertDatabaseCount('task_completions', 0);
    }

    public function test_student_cannot_complete_another_batchs_task_via_chat(): void
    {
        [$student] = $this->studentAndRep();
        $otherBatch = Batch::factory()->create();
        $otherRep = User::factory()->create(['batch_id' => $otherBatch->id, 'role' => Role::Representative]);
        $foreignTask = Task::factory()->create([
            'batch_id' => $otherBatch->id, 'created_by' => $otherRep->id, 'title' => 'Foreign Batch Assignment',
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'I finished the Foreign Batch assignment',
        ])->assertOk();

        // Search is batch-scoped, so it's reported as not found — never leaked.
        $this->assertStringContainsString("couldn't find", mb_strtolower($response->json('data.message')));
        $this->assertDatabaseMissing('task_completions', ['task_id' => $foreignTask->id]);
    }

    // ------------------------------------------------------------- queries

    public function test_most_urgent_query_returns_the_real_nearest_task(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Far Away Exam', 'deadline' => now()->addDays(20),
        ]);
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Nearest Quiz', 'deadline' => now()->addHours(3),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What is my most urgent task?'])->assertOk();

        $this->assertStringContainsString('Nearest Quiz', $response->json('data.message'));
        $this->assertStringNotContainsString('Far Away Exam', $response->json('data.message'));
    }

    public function test_urgent_query_excludes_completed_tasks(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        $done = Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Already Done Quiz', 'deadline' => now()->addHour(),
        ]);
        $done->completions()->create(['student_id' => $student->id, 'completed_at' => now()]);
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Still Pending Exam', 'deadline' => now()->addDays(3),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'Which task is most urgent?'])->assertOk();

        $this->assertStringContainsString('Still Pending Exam', $response->json('data.message'));
        $this->assertStringNotContainsString('Already Done Quiz', $response->json('data.message'));
    }

    public function test_this_week_query_returns_real_tasks_due_this_week(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'This Week Task', 'deadline' => now()->addDays(2),
        ]);
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Far Future Task', 'deadline' => now()->addDays(60),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What do I have this week?'])->assertOk();

        $this->assertStringContainsString('This Week Task', $response->json('data.message'));
        $this->assertStringNotContainsString('Far Future Task', $response->json('data.message'));
    }

    public function test_tomorrow_query_works(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Due Tomorrow Task', 'deadline' => now()->addDay(),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What do I have tomorrow?'])->assertOk();

        $this->assertStringContainsString('Due Tomorrow Task', $response->json('data.message'));
    }

    public function test_overdue_query_returns_real_overdue_tasks(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Late Assignment', 'deadline' => now()->subDay(),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What is overdue?'])->assertOk();

        $this->assertStringContainsString('Late Assignment', $response->json('data.message'));
    }

    public function test_today_query_reports_nothing_due_when_true(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'deadline' => now()->addDays(10),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What is due today?'])->assertOk();

        $this->assertStringContainsString('nothing due today', mb_strtolower($response->json('data.message')));
    }

    public function test_list_query_never_returns_another_batchs_tasks(): void
    {
        [$student] = $this->studentAndRep();
        $otherBatch = Batch::factory()->create();
        $otherRep = User::factory()->create(['batch_id' => $otherBatch->id, 'role' => Role::Representative]);
        Task::factory()->create([
            'batch_id' => $otherBatch->id, 'created_by' => $otherRep->id, 'title' => 'Secret Other Batch Task',
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'Show my tasks'])->assertOk();

        $this->assertStringNotContainsString('Secret Other Batch Task', $response->json('data.message'));
    }

    public function test_next_task_query_phrasing_works(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id,
            'title' => 'Immediate Task', 'deadline' => now()->addHour(),
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What is my next task?'])->assertOk();

        $this->assertStringContainsString('Immediate Task', $response->json('data.message'));
    }

    public function test_what_tasks_do_i_have_phrasing_is_a_query_not_an_add_command(): void
    {
        [$student, $rep, $batch] = $this->studentAndRep();
        Task::factory()->create([
            'batch_id' => $batch->id, 'created_by' => $rep->id, 'title' => 'Existing Task',
        ]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What tasks do I have?'])->assertOk();

        $this->assertFalse($response->json('data.taskChanged'));
        $this->assertStringContainsString('Existing Task', $response->json('data.message'));
        // Must not have been misread as "add a task" and created a new row.
        $this->assertDatabaseCount('tasks', 1);
    }

    // ---------------------------------------------------- general fallback

    public function test_general_academic_question_gets_a_useful_local_answer_without_openai(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'What is the Pomodoro technique?'])->assertOk();

        $this->assertStringContainsStringIgnoringCase('25-minute', $response->json('data.message'));
        Http::assertNothingSent();
    }

    public function test_unrecognized_message_gets_the_default_capability_summary(): void
    {
        [$student] = $this->studentAndRep();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/assistant/chat', ['message' => 'asdkjaslkdj random gibberish'])->assertOk();

        $this->assertStringContainsString('I can help you', $response->json('data.message'));
    }

    // ------------------------------------------------------------ auth/guest

    public function test_guest_cannot_use_any_chatbot_command(): void
    {
        $this->postJson('/api/assistant/chat', ['message' => 'Add a quiz for Friday'])->assertUnauthorized();
        $this->assertDatabaseCount('tasks', 0);
    }
}
