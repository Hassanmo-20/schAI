import { apiClient } from './apiClient';

export interface AssistantTurn {
  role: 'user' | 'assistant';
  content: string;
}

/** Mirrors the caps enforced by the backend ChatRequest. */
export const ASSISTANT_LIMITS = {
  maxMessageChars: 2000,
  maxHistoryMessages: 6,
} as const;

export interface AssistantError extends Error {
  status?: number;
  rateLimited?: boolean;
}

/**
 * Academic Assistant client.
 *
 * The OpenAI key lives only on the Laravel server — the browser talks solely
 * to our own authenticated endpoint, which builds the academic context itself.
 */
export const assistantService = {
  async sendMessage(message: string, history: AssistantTurn[] = []): Promise<string> {
    const trimmed = message.trim();
    if (!trimmed) {
      throw new Error('Please enter a question for the assistant.');
    }

    try {
      const response = await apiClient.post<{ data: { message: string } }>('/assistant/chat', {
        message: trimmed.slice(0, ASSISTANT_LIMITS.maxMessageChars),
        history: history.slice(-ASSISTANT_LIMITS.maxHistoryMessages),
      });

      const reply = response?.data?.message;
      if (!reply) {
        throw new Error('The assistant returned an empty response. Please try again.');
      }
      return reply;
    } catch (err: any) {
      const status: number | undefined = err?.status;

      // Map transport/HTTP failures onto messages that are safe to display.
      const friendly: AssistantError = new Error(
        status === 429
          ? "You've reached the assistant's request limit. Please wait a moment and try again."
          : status === 401
            ? 'Your session expired. Please sign in again to use the assistant.'
            : status === 422
              ? err?.message || 'That message could not be sent. Please rephrase and try again.'
              : status === 503 || status === 502
                ? err?.message || 'The academic assistant is temporarily unavailable. Please try again.'
                : "Sorry, I couldn't reach the academic assistant right now. Please try again."
      );
      friendly.status = status;
      friendly.rateLimited = status === 429;
      throw friendly;
    }
  },
};
