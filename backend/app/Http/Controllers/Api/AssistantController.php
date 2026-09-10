<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\ChatRequest;
use App\Services\Assistant\AssistantChatService;
use Illuminate\Http\JsonResponse;

/**
 * Academic Assistant chat endpoint.
 *
 * All logic lives in AssistantChatService (intent routing), which always
 * returns a safe, ready-to-display message — this controller never needs to
 * translate provider errors into HTTP status codes itself.
 */
class AssistantController extends Controller
{
    public function __construct(private readonly AssistantChatService $assistant) {}

    public function chat(ChatRequest $request): JsonResponse
    {
        $result = $this->assistant->handle(
            $request->user(),
            $request->validated('message'),
            $request->validated('history') ?? [],
        );

        return response()->json(['data' => $result]);
    }
}
