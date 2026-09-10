<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One notification row, flattened for the UI.
 *
 * The `data` payload is written by our own notification classes (see
 * App\Notifications), so its keys are known — they are surfaced directly
 * rather than nesting an opaque blob.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? 'notification',
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'task_id' => $data['task_id'] ?? null,
            'task_title' => $data['task_title'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
