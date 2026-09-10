<?php

namespace Database\Seeders;

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
 */
class SchAIDemoSeeder extends Seeder
{
    public function run(): void
    {
        $batch = Batch::firstOrCreate(
            ['name' => 'Batch CS-2026-A'],
            ['department' => 'Computer Science', 'academic_year' => '2026']
        );

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

        // A second batch so cross-batch isolation can be exercised manually.
        $other = Batch::firstOrCreate(
            ['name' => 'Batch CS-2026-B'],
            ['department' => 'Computer Science', 'academic_year' => '2026']
        );

        $otherRep = User::firstOrCreate(
            ['email' => 'rep.b@schai.test'],
            [
                'name' => 'Batch B Rep',
                'password' => Hash::make('password'),
                'role' => Role::Representative,
                'batch_id' => $other->id,
            ]
        );

        Task::firstOrCreate(
            ['title' => 'Batch B only: Networks Worksheet', 'batch_id' => $other->id],
            [
                'description' => 'Should never be visible to batch A users.',
                'type' => TaskType::Assignment,
                'deadline' => now()->addDays(3),
                'created_by' => $otherRep->id,
                'is_active' => true,
            ]
        );
    }
}
