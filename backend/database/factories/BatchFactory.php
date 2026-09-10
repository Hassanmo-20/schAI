<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Batch '.fake()->unique()->bothify('??-####-?'),
            'department' => fake()->randomElement(['Computer Science', 'Software Engineering', 'Information Technology']),
            'academic_year' => '2026',
        ];
    }
}
