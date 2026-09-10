<?php

namespace App\Http\Resources;

use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attachments are served through an authorized download route — the raw
 * storage path is never exposed to clients.
 *
 * @mixin TaskAttachment
 */
class TaskAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'url' => $this->downloadUrl(),
            'mime_type' => $this->mime_type,
            'file_size' => (int) $this->file_size,
        ];
    }
}
