<?php

namespace App\Services;

use App\Exceptions\AssistantUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin OpenAI Chat Completions client.
 *
 * Uses Laravel's native HTTP client rather than pulling in an SDK. Everything
 * provider-specific (auth, model, timeouts, error shapes) is contained here so
 * controllers never touch OpenAI directly, and so the API key cannot leak into
 * responses or logs.
 */
class OpenAIService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * Send a prepared message list and return the assistant's reply text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws AssistantUnavailableException
     */
    public function chat(array $messages): string
    {
        if (! $this->isConfigured()) {
            throw AssistantUnavailableException::notConfigured();
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout((int) config('services.openai.timeout'))
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/chat/completions', [
                    'model' => config('services.openai.model'),
                    'messages' => $messages,
                    'max_tokens' => (int) config('services.openai.max_output_tokens'),
                    // Low temperature: this assistant reports real deadlines,
                    // so determinism matters more than creative phrasing.
                    'temperature' => 0.3,
                ]);
        } catch (ConnectionException $e) {
            // Timeouts / DNS / TLS failures. Log the class only, never the key.
            Log::warning('OpenAI connection failure', ['reason' => $e->getMessage()]);

            throw AssistantUnavailableException::unreachable();
        } catch (Throwable $e) {
            Log::warning('OpenAI unexpected client error', ['type' => $e::class]);

            throw AssistantUnavailableException::unreachable();
        }

        if ($response->failed()) {
            // Provider error bodies can echo request content; log status only.
            Log::warning('OpenAI request failed', ['status' => $response->status()]);

            throw AssistantUnavailableException::unreachable();
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            Log::warning('OpenAI returned an unusable payload');

            throw AssistantUnavailableException::badResponse();
        }

        return trim($content);
    }
}
