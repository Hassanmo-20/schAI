<?php

namespace Database\Factories;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Cursor into the canonical (batch year, department) pairs.
     *
     * A batch row IS a group, and `batches_group_unique` allows only one row
     * per pair — so the factory hands out distinct pairs in sequence rather
     * than picking randomly, which would collide as soon as a test created two
     * batches. Ten pairs exist (5 years x 2 departments), which is far more
     * than any single test needs.
     */
    private static int $cursor = 0;

    public function definition(): array
    {
        [$batchYear, $department] = $this->nextPair();

        return [
            'name' => Batch::groupLabelFor($batchYear, $department),
            'batch_year' => $batchYear,
            'department' => $department,
            'academic_year' => $batchYear->value,
        ];
    }

    /** Pin the factory to one specific group. */
    public function group(BatchYear $batchYear, Department $department): static
    {
        return $this->state(fn () => [
            'name' => Batch::groupLabelFor($batchYear, $department),
            'batch_year' => $batchYear,
            'department' => $department,
            'academic_year' => $batchYear->value,
        ]);
    }

    /** @return array{0: BatchYear, 1: Department} */
    private function nextPair(): array
    {
        $pairs = [];

        foreach (BatchYear::cases() as $batchYear) {
            foreach (Department::cases() as $department) {
                $pairs[] = [$batchYear, $department];
            }
        }

        return $pairs[self::$cursor++ % count($pairs)];
    }
}
