<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(TaskType::cases()),
            'deadline' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'batch_id' => Batch::factory(),
            'created_by' => User::factory()->representative(),
            'is_active' => true,
        ];
    }
}
