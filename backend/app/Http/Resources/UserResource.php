<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public user shape. The password hash is hidden by the model and is never
 * included here. `batch` is the display name so the existing frontend can
 * bind it directly; `batch_id` is the relational key.
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
            // Display name for direct frontend binding (controllers eager-load `batch`).
            'batch' => $this->batch?->name,
        ];
    }
}
