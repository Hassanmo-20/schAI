<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal public batch shape for the registration picker.
 *
 * @mixin Batch
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'department' => $this->department,
            'academic_year' => $this->academic_year,
        ];
    }
}
