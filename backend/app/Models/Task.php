<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'deadline',
        'batch_id',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'deadline' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('batch_id', $batchId);
    }
}
