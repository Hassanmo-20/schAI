import React, { useEffect, useRef, useState } from 'react';
import { assistantService, AssistantTurn, ASSISTANT_LIMITS } from '../../services/assistantService';
import { useTasks } from '../../context/TaskContext';
import Markdown from '../common/Markdown';
import './tasks.css';

interface ChatMsg {
  role: 'user' | 'assistant';
  text: string;
}

const SUGGESTIONS = [
  'What should I work on today?',
  'Which task is most urgent?',
  'What do I have this week?',
  'Add a quiz for Friday',
];

/**
 * Academic Assistant.
 *
 * Talks only to our own authenticated endpoint (`POST /api/assistant/chat`),
 * which builds the academic context server-side. No provider key is ever
 * present in the browser.
 */
const AcademicAssistant: React.FC = () => {
  const { fetchTasks } = useTasks();
  const [messages, setMessages] = useState<ChatMsg[]>([]);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rateLimited, setRateLimited] = useState(false);

  const threadRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    // Keep the newest turn in view as the conversation grows.
    threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages, sending]);

  const ask = async (question: string) => {
    const text = question.trim();
    if (!text || sending) return;

    setError(null);
    setRateLimited(false);
    setDraft('');

    // History sent to the API excludes the message we are about to ask.
    const history: AssistantTurn[] = messages
      .slice(-ASSISTANT_LIMITS.maxHistoryMessages)
      .map((m) => ({ role: m.role, content: m.text }));

    setMessages((prev) => [...prev, { role: 'user', text }]);
    setSending(true);

    try {
      const reply = await assistantService.sendMessage(text, history);
      setMessages((prev) => [...prev, { role: 'assistant', text: reply.message }]);
      if (reply.taskChanged) {
        // A task was really created/completed by this command — refresh from
        // the backend so the dashboard/task list reflect it immediately.
        void fetchTasks();
      }
    } catch (err: any) {
      setError(err?.message ?? 'Something went wrong. Please try again.');
      setRateLimited(Boolean(err?.rateLimited));
    } finally {
      setSending(false);
      inputRef.current?.focus();
    }
  };

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    void ask(draft);
  };

  const onKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Enter sends, Shift+Enter inserts a newline.
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void ask(draft);
    }
  };

  const isEmpty = messages.length === 0;

  return (
    <section className="card assistant" aria-labelledby="assistant-heading">
      <div className="assistant-head">
        <h2 id="assistant-heading">Academic Assistant</h2>
        <p className="assistant-sub">
          Ask about your tasks, or say things like &quot;I have a quiz tomorrow&quot; or
          &quot;I finished the database assignment&quot;.
        </p>
      </div>

      <div className="thread" ref={threadRef} role="log" aria-live="polite" aria-busy={sending}>
        {isEmpty && !sending && (
          <div className="assistant-empty">
            <p className="assistant-empty-title">Ask me about your tasks, deadlines, or study plan.</p>
            <p className="assistant-empty-hint">
              I can only see your own SchAI tasks — I&apos;ll tell you if something isn&apos;t there.
            </p>
            <div className="assistant-suggestions">
              {SUGGESTIONS.map((s) => (
                <button
                  key={s}
                  type="button"
                  className="suggestion-chip"
                  onClick={() => void ask(s)}
                  disabled={sending}
                >
                  {s}
                </button>
              ))}
            </div>
          </div>
        )}

        {messages.map((m, i) => (
          <div key={i} className={`msg ${m.role === 'user' ? 'user' : 'bot'}`}>
            {m.role === 'assistant' && <div className="avatar" aria-hidden="true">AI</div>}
            <div className="bubble">
              {m.role === 'assistant' ? <Markdown text={m.text} /> : m.text}
            </div>
          </div>
        ))}

        {sending && (
          <div className="msg bot">
            <div className="avatar" aria-hidden="true">AI</div>
            <div className="bubble bubble-thinking">
              <span className="dot" /><span className="dot" /><span className="dot" />
              <span className="sr-only">The assistant is thinking…</span>
            </div>
          </div>
        )}
      </div>

      {error && (
        <p className={`assistant-error${rateLimited ? ' assistant-error-limit' : ''}`} role="alert">
          {error}
        </p>
      )}

      <form className="composer composer-multiline" onSubmit={onSubmit}>
        <label className="sr-only" htmlFor="assistant-input">
          Ask the academic assistant a question
        </label>
        <textarea
          id="assistant-input"
          ref={inputRef}
          rows={1}
          placeholder="Ask about your tasks, deadlines or study plan…"
          value={draft}
          maxLength={ASSISTANT_LIMITS.maxMessageChars}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={onKeyDown}
          disabled={sending}
        />
        <button
          className="btn btn-primary btn-sm"
          type="submit"
          disabled={sending || draft.trim().length === 0}
          aria-label="Send message to the academic assistant"
        >
          {sending ? 'Sending…' : 'Send'}
        </button>
      </form>
    </section>
  );
};

export default AcademicAssistant;
