# Academic Assistant (OpenAI)

The assistant answers questions about the signed-in user's **own** SchAI tasks —
"what should I work on today?", "which task is most urgent?", "make me a study
plan". It is powered by the OpenAI Chat Completions API, called **only from the
Laravel backend**.

## Configuration

Add to `backend/.env` (never to `.env.example`, never to the frontend):

```env
OPENAI_API_KEY=sk-...your-key...
OPENAI_MODEL=gpt-4o-mini
```

Optional: `OPENAI_BASE_URL`, `OPENAI_TIMEOUT` (default 30s),
`OPENAI_MAX_OUTPUT_TOKENS` (default 700). Config lives in
`config/services.php` under `openai`.

**The assistant is optional.** With no key configured, every other SchAI
feature works normally and the endpoint returns `503` with
*"The academic assistant is not configured on this server yet."*

## Request flow

```
React (AcademicAssistant.tsx)
  └─ assistantService.sendMessage()
       └─ POST /api/assistant/chat        (Sanctum bearer token)
            ├─ ChatRequest                validation
            ├─ AcademicContextBuilder     reads DB for the token holder only
            ├─ AssistantPromptBuilder     system + context + history + message
            └─ OpenAIService              the only code that talks to OpenAI
                 └─ reply → { "data": { "message": "..." } }
```

The API key exists only in the Laravel process. The browser never sees it and
never contacts OpenAI directly.

## Endpoint

`POST /api/assistant/chat` — authenticated (any role).

```json
{
  "message": "What should I work on today?",
  "history": [{ "role": "user", "content": "..." }]
}
```

| Field | Rules |
|---|---|
| `message` | required, string, trimmed, 1–2000 chars |
| `history` | optional, max 6 turns, `role` ∈ `user`/`assistant`, content ≤ 800 chars |

Responses: `200 { data: { message } }` · `401` unauthenticated · `422`
validation · `429` rate limited · `502` unreadable provider reply ·
`503` not configured / provider unreachable.

`history` is only used for follow-up context ("how long should I spend on
*it*?"). Conversations are **not** persisted — no new database tables.

## Rate limits

Named limiter `assistant` in `AppServiceProvider`, keyed per user:

- **10 requests / minute**
- **200 requests / day**

Enforced server-side; exceeding either returns `429`. These sit alongside the
general `api` limiter (120/min).

## What data the AI receives

Built server-side from the authenticated user — **never** from the request body.

**Students**: their name, role, batch name, current datetime, a workload summary
(total/completed/pending/overdue) and up to **25** active tasks from their own
batch as `title | type | due | status | details`, with descriptions truncated to
180 characters.

**Representatives**: the same shape for their own batch, plus `active` and
aggregate `completed_by: N/M students` — exactly the statistics they can already
read through the API.

**Never sent:** other students' names, emails or individual progress; other
batches; passwords; tokens; internal IDs; raw model dumps.

## Security boundaries

- **Isolation** — context comes from `Auth::user()` and mirrors `TaskPolicy`
  scoping. A client cannot supply or override it; extra body fields are ignored.
- **Prompt injection** — task titles/descriptions are authored by
  representatives and treated as untrusted **data**: control characters and
  newlines are flattened, length is capped, and the content is placed in a
  labelled data block separate from the system instructions. The system prompt
  tells the model to never follow instructions found in that block.
- **Prompt secrecy** — the model is instructed to refuse requests to reveal the
  system prompt, and the prompt is never returned in a response.
- **No hallucinated data** — the model is told to say it cannot find something
  rather than invent deadlines, grades or courses.
- **Error handling** — provider status codes and bodies are logged (status only)
  and never forwarded to the client; the API key is never logged.
- **Client-supplied history** is role-whitelisted, so a `system` turn cannot be
  smuggled in.

## Running the tests without a key

`tests/Feature/AssistantTest.php` fakes the HTTP layer with `Http::fake()` and
`Http::preventStrayRequests()`, so `php artisan test` never contacts OpenAI and
never costs money. No `OPENAI_API_KEY` is required to run the suite.
