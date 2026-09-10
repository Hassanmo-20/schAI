<?php

namespace App\Http\Resources;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student task shape. Deadlines are ISO-8601 so the frontend can compute
 * urgency itself.
 *
 * Controllers attach two optional query aliases (never columns):
 * - `viewer_completed_at`: the current student's completion timestamp, if any.
 * - `completed_students` + `total_students`: aggregates for representative listings.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerCompletedAt = $this->getAttribute('viewer_completed_at');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'deadline' => $this->deadline?->toIso8601String(),
            'batch_id' => $this->batch_id,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'is_completed' => $viewerCompletedAt !== null,
            'completed_at' => $viewerCompletedAt
                ? Carbon::parse($viewerCompletedAt)->toIso8601String()
                : null,
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'statistics' => $this->when(
                $this->getAttribute('completed_students') !== null
                    && $this->getAttribute('total_students') !== null,
                fn () => $this->statisticsPayload()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function statisticsPayload(): array
    {
        $completed = (int) $this->getAttribute('completed_students');
        $total = (int) $this->getAttribute('total_students');
        $remaining = max(0, $total - $completed);

        return [
            'total_students' => $total,
            'completed_students' => $completed,
            'remaining_students' => $remaining,
            // Guarded: an empty batch reports 0%, never NaN/Infinity.
            'completion_percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }
}
