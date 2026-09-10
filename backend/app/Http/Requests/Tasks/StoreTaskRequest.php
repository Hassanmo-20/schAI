<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role + batch checks live in TaskPolicy::create; keep the request focused on shape.
        return $this->user() !== null;
    }

    /**
     * Accept the frontend's display casing ("Assignment") while storing the
     * canonical lowercase enum value.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('type'))) {
            $this->merge(['type' => strtolower(trim($this->input('type')))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'deadline' => ['required', 'date', 'after:now'],
            // NOTE: no `batch_id`, no `created_by` — both are derived server-side.
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf',
                'extensions:jpg,jpeg,png,gif,webp,pdf',
            ],
        ];
    }
}
