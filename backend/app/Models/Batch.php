<?php

namespace App\Models;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An academic group: one (batch year, department) pair.
 *
 * The group is the isolation boundary of the whole application. Every task,
 * completion and notification belongs to exactly one batch row, and users only
 * ever see rows whose `batch_id` matches their own — so "2027 CCE" and
 * "2027 CSE" are fully separate worlds even though they share a batch year.
 */
class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'batch_year',
        'department',
        'academic_year',
    ];

    protected function casts(): array
    {
        return [
            'batch_year' => BatchYear::class,
            'department' => Department::class,
        ];
    }

    /**
     * Find (or create) the group for a batch year + department pair.
     *
     * This is the ONLY way a user is attached to a group: registration sends
     * the pair, never a raw id, so a client can never invent group membership
     * that doesn't correspond to a real batch/department combination.
     */
    public static function resolveGroup(BatchYear $batchYear, Department $department): self
    {
        return static::firstOrCreate(
            ['batch_year' => $batchYear->value, 'department' => $department->value],
            [
                'name' => static::groupLabelFor($batchYear, $department),
                'academic_year' => $batchYear->value,
            ]
        );
    }

    /** Display label for a group, e.g. "2028++ CCE". */
    public static function groupLabelFor(BatchYear $batchYear, Department $department): string
    {
        return "{$batchYear->value} {$department->value}";
    }

    /** This group's display label, falling back to `name` for legacy rows. */
    public function groupLabel(): string
    {
        return $this->batch_year !== null && $this->department !== null
            ? static::groupLabelFor($this->batch_year, $this->department)
            : (string) $this->name;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::Student->value);
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::Representative->value);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
