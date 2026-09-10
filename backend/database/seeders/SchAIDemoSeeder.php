<?php

namespace Database\Seeders;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use App\Enums\TaskType;
use App\Models\Batch;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic development dataset mirroring the frontend mock scenarios:
 * tonight / tomorrow / 2-day / 7-day / overdue tasks with varied completion.
 *
 * Also creates every offered group (5 batch years x 2 departments) and seeds
 * neighbours that differ from the demo group by department only and by batch
 * year only, so isolation can be checked manually along both axes.
 */
class SchAIDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Every group the registration form offers, so the picker is populated
        // on a freshly seeded database.
        foreach (BatchYear::cases() as $batchYear) {
            foreach (Department::cases() as $department) {
                Batch::resolveGroup($batchYear, $department);
            }
        }

        $batch = Batch::resolveGroup(BatchYear::Y2027, Department::CCE);

        $rep = User::firstOrCreate(
            ['email' => 'representative@schai.test'],
            [
                'name' => 'Sarah Connor',
                'password' => Hash::make('password'),
                'role' => Role::Representative,
                'batch_id' => $batch->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'student@schai.test'],
            [
                'name' => 'Alex Mercer',
                'password' => Hash::make('password'),
                'role' => Role::Student,
                'batch_id' => $batch->id,
            ]
        );

        // Top the batch up to 50 students (the demo login above is included).
        $existing = User::where('batch_id', $batch->id)->where('role', Role::Student)->count();
        User::factory()
            ->count(max(0, 50 - $existing))
            ->create(['batch_id' => $batch->id, 'role' => Role::Student]);

        $demoStudent = User::where('email', 'student@schai.test')->value('id');

        // Other students supply the bulk of completions. The demo student is
        // handled separately per task so the demo login has a realistic mix of
        // pending/overdue/completed work instead of a 100%-complete dashboard.
        $otherStudentIds = User::where('batch_id', $batch->id)
            ->where('role', Role::Student)
            ->where('id', '!=', $demoStudent)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $tasks = [
            [
                'title' => 'Database Assignment 2: Relational Normalization & SQL Queries',
                'description' => 'Deconstruct unnormalized tables to 3NF/BCNF and write SQL scripts for complex joins, window functions, and indexing strategies.',
                'type' => TaskType::Assignment,
                'deadline' => now()->addHours(6),
                'completed' => 38,
                'demo_done' => false, // due tonight → shows as very urgent
            ],
            [
                'title' => 'OOP Quiz: Polymorphism, Virtual Functions & Templates',
                'description' => 'Online timed quiz (45 minutes) covering OOP principles, abstract classes, operator overloading, and templates in C++.',
                'type' => TaskType::Quiz,
                'deadline' => now()->addHours(26),
                'completed' => 20,
                'demo_done' => false, // due tomorrow
            ],
            [
                'title' => 'Programming Assignment: Distributed Cache Key-Value Store',
                'description' => 'Build a distributed key-value cache with consistent hashing, LRU eviction, and replica failover simulation.',
                'type' => TaskType::Assignment,
                'deadline' => now()->addHours(48),
                'completed' => 45,
                'demo_done' => true, // already handed in
            ],
            [
                'title' => 'Midterm Exam: Algorithms & Data Structures',
                'description' => 'Physical midterm in Auditorium B: asymptotic analysis, heaps, balanced BSTs, graph traversals, dynamic programming.',
                'type' => TaskType::Midterm,
                'deadline' => now()->addDays(7),
                'completed' => 5,
                'demo_done' => false,
            ],
            [
                'title' => 'Old Assignment: Computer Architecture MIPS Pipeline Simulation',
                'description' => 'Five-stage MIPS pipeline with forwarding unit and hazard detection. Late submissions incur a 10% daily penalty.',
                'type' => TaskType::Assignment,
                'deadline' => now()->subDay(),
                'completed' => 48,
                'demo_done' => false, // overdue and unfinished
            ],
            [
                'title' => 'Final Examination: Operating Systems & Concurrency',
                'description' => 'Cumulative final: virtual memory, page replacement, mutexes, condition variables, deadlock avoidance.',
                'type' => TaskType::Exam,
                'deadline' => now()->addDays(30),
                'completed' => 1,
                'demo_done' => false,
            ],
        ];

        foreach ($tasks as $data) {
            $task = Task::firstOrCreate(
                ['title' => $data['title'], 'batch_id' => $batch->id],
                [
                    'description' => $data['description'],
                    'type' => $data['type'],
                    'deadline' => $data['deadline'],
                    'created_by' => $rep->id,
                    'is_active' => true,
                ]
            );

            // Keep the documented headline figures (38/50, 20/50, …) exact:
            // the demo student consumes one slot when they have completed it.
            $completers = $data['demo_done'] ? [$demoStudent] : [];
            $fromOthers = max(0, $data['completed'] - count($completers));
            $completers = array_merge($completers, array_slice($otherStudentIds, 0, $fromOthers));

            foreach ($completers as $studentId) {
                TaskCompletion::firstOrCreate(
                    ['task_id' => $task->id, 'student_id' => $studentId],
                    ['completed_at' => now()->subHours(random_int(1, 72))]
                );
            }
        }

        // Neighbouring groups so cross-group isolation can be exercised by hand
        // along BOTH axes: same year / different department, and same
        // department / different year. Neither should ever see the tasks above.
        $this->seedNeighbourGroup(
            Batch::resolveGroup(BatchYear::Y2027, Department::CSE),
            'rep.2027cse@schai.test',
            '2027 CSE Rep',
            '2027 CSE only: Signals & Systems Worksheet',
        );

        $this->seedNeighbourGroup(
            Batch::resolveGroup(BatchYear::Y2028, Department::CCE),
            'rep.2028cce@schai.test',
            '2028 CCE Rep',
            '2028 CCE only: Networks Worksheet',
        );
    }

    /** A group with one representative and one task nobody outside it may see. */
    private function seedNeighbourGroup(Batch $group, string $email, string $repName, string $taskTitle): void
    {
        $rep = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $repName,
                'password' => Hash::make('password'),
                'role' => Role::Representative,
                'batch_id' => $group->id,
            ]
        );

        User::factory()->count(5)->create([
            'batch_id' => $group->id,
            'role' => Role::Student,
        ]);

        Task::firstOrCreate(
            ['title' => $taskTitle, 'batch_id' => $group->id],
            [
                'description' => "Should never be visible outside {$group->groupLabel()}.",
                'type' => TaskType::Assignment,
                'deadline' => now()->addDays(3),
                'created_by' => $rep->id,
                'is_active' => true,
            ]
        );
    }
}
