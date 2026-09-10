<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\ChatRequest;
use App\Services\AssistantPromptBuilder;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;

/**
 * Academic Assistant chat endpoint.
 *
 * Deliberately thin: context building lives in AcademicContextBuilder, prompt
 * assembly in AssistantPromptBuilder, and provider I/O in OpenAIService.
 */
class AssistantController extends Controller
{
    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly AssistantPromptBuilder $prompts,
    ) {}

    public function chat(ChatRequest $request): JsonResponse
    {
        // Context is derived from the token holder only — never from the body.
        $messages = $this->prompts->build(
            $request->user(),
            $request->validated('message'),
            $request->validated('history') ?? [],
        );

        try {
            $reply = $this->openAI->chat($messages);
        } catch (AssistantUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json([
            'data' => [
                'message' => $reply,
            ],
        ]);
    }
}
