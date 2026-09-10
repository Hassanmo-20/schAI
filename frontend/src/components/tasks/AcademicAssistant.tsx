import React, { useState } from 'react';
import './tasks.css';

interface ChatMsg {
  role: 'user' | 'bot';
  text: string;
}

/**
 * Frontend placeholder for a future academic AI assistant.
 * No backend calls — clearly labelled as "coming soon".
 */
const AcademicAssistant: React.FC = () => {
  const [messages, setMessages] = useState<ChatMsg[]>([
    { role: 'bot', text: 'Hi! I will soon help you plan study sessions around your deadlines. (Frontend placeholder — no AI backend connected yet.)' },
  ]);
  const [draft, setDraft] = useState('');

  const send = (e: React.FormEvent) => {
    e.preventDefault();
    const text = draft.trim();
    if (!text) return;
    setMessages((m) => [
      ...m,
      { role: 'user', text },
      {
        role: 'bot',
        text: 'Thanks — the academic assistant is not connected yet. Your deadlines on the left come from your real task list.',
      },
    ]);
    setDraft('');
  };

  return (
    <section className="card assistant" aria-label="Academic assistant (placeholder)">
      <div className="assistant-head">
        <h2>Academic Assistant <span className="soon-badge">soon</span></h2>
      </div>
      <div className="thread" aria-live="polite">
        {messages.map((m, i) => (
          <div key={i} className={`msg ${m.role}`}>
            {m.role === 'bot' && <div className="avatar" aria-hidden="true">AI</div>}
            <div className="bubble">{m.text}</div>
          </div>
        ))}
      </div>
      <form className="composer" onSubmit={send}>
        <input
          type="text"
          placeholder="Ask about your study plan…"
          aria-label="Message the academic assistant"
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
        />
        <button className="btn btn-primary btn-sm" type="submit">Send</button>
      </form>
    </section>
  );
};

export default AcademicAssistant;
