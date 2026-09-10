<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public user shape. The password hash is hidden by the model and is never
 * included here.
 *
 * The group the user belongs to is exposed as its parts (`batch_year`,
 * `department`) plus a ready-made display label, so the UI can show
 * "2027 CCE" without re-deriving it.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'batch_id' => $this->batch_id,
            'batch_year' => $this->batch?->batch_year?->value,
            'department' => $this->batch?->department?->value,
            // Display label for direct frontend binding (controllers eager-load `batch`).
            'batch' => $this->batch?->groupLabel(),
        ];
    }
}
