<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('type'))) {
            $this->merge(['type' => strtolower(trim($this->input('type')))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'required', Rule::enum(TaskType::class)],
            'deadline' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            // NOTE: `created_by`, `batch_id` and completion data are not updatable here.
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf',
                'extensions:jpg,jpeg,png,gif,webp,pdf',
            ],
        ];
    }
}
