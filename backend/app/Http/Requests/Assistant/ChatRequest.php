<?php

namespace App\Http\Requests\Assistant;

use App\Services\AssistantPromptBuilder;
use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public const MAX_MESSAGE_CHARS = 2000;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:'.self::MAX_MESSAGE_CHARS],

            // Optional recent turns so follow-up questions make sense. Bounded
            // here as well as in the prompt builder to cap request size.
            'history' => ['sometimes', 'array', 'max:'.AssistantPromptBuilder::MAX_HISTORY_MESSAGES],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => [
                'required_with:history',
                'string',
                'max:'.AssistantPromptBuilder::MAX_HISTORY_CHARS,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a question for the assistant.',
            'message.max' => 'That message is too long. Please shorten it and try again.',
        ];
    }
}
