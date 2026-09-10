<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function downloadUrl(): string
    {
        return route('api.tasks.attachments.download', [
            'task' => $this->task_id,
            'attachment' => $this->id,
        ]);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk(config('filesystems.default'))->exists($this->file_path);
    }
}
