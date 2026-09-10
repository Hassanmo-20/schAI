<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal public group shape for the registration picker: the (batch year,
 * department) pair and its label. No membership data and no counts — nothing
 * here helps enumerate users.
 *
 * @mixin Batch
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->groupLabel(),
            'batch_year' => $this->batch_year?->value,
            'department' => $this->department?->value,
            'academic_year' => $this->academic_year,
        ];
    }
}
