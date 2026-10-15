# Kemet Tech sales desk

Personal sales desk for Kemet Tech. Phase 1 is the CRM. Phase 2 adds the official WhatsApp Business Cloud API. Phase 3 adds a language model that proposes structured outcomes. Laravel still decides what is stored and what is sent.

Nelson Mboya is the first operator. The seeded owner is created from `SEED_USER_EMAIL` and `SEED_USER_PASSWORD`.

## Phase 1

- Login, logout, and password reset (the reset link is written to the log when `MAIL_MAILER=log`).
- Products with a name, slug, description, active flag, and optional pricing notes. No prices are hardcoded and there is no checkout.
- Pipelines, stages (active, won, lost, archived-nurture), reorder, archive, and permitted transitions. A stage can store a follow-up delay in hours. A stage-delay rule uses that number.
- Contacts with E.164 phones. Local Kenyan numbers default to +254; other markets use `+` country code. Classify each client by country and conversation area for multi-country ads. Desk notes on a contact are read by the live WhatsApp assistant and lead analysis. The same number updates the existing contact. The same name does not.
- Manual merge keeps leads, notes, and message history on the surviving contact and writes a merge audit row.
- Leads tied to a contact, product, and pipeline stage. One open lead per contact and product. A closed lead does not block a new one.
- Stage changes go through `SalesStageTransitionService`, inside a database transaction, with history and the actor. Lost stages require a reason. Illegal moves are rejected and do not write history.
- Messages and notes are ordinary database writes. AI is not involved.
- An activity log on the lead profile.
- A desktop-first command centre: total leads, new leads today, follow-ups due today, overdue follow-ups, leads by stage, won and lost. Dates display in Africa/Nairobi. Timestamps are stored in UTC.

## Phase 2

WhatsApp inbound and outbound through a selectable driver: Meta Cloud API (`MESSAGING_DRIVER=meta`), FlareSend (`flaresend`), or Twilio (`twilio`). Text in and out. No WhatsApp Web and no browser automation.

Point FlareSend at `POST /webhooks/flaresend` and Twilio at `POST /webhooks/twilio`. Meta still uses `GET/POST /webhooks/whatsapp`.

- `GET /webhooks/whatsapp` verifies the subscription with `WHATSAPP_VERIFY_TOKEN`.
- `POST /webhooks/whatsapp` checks `X-Hub-Signature-256` against `WHATSAPP_APP_SECRET`. A bad signature is rejected and stored nowhere.
- A valid body is saved in `webhook_events` with a unique hash before anything else. The same delivery does not create a second contact, lead, or message. Processing runs on the queue. If the queue push fails, the row stays and `php artisan whatsapp:retry` can run it. The message row is written by that job. No model is called.
- An unknown `wa_id` becomes a contact (E.164, Kenya +254) and one open lead on the default pipeline at New, source `whatsapp`.
- A known contact with an open lead gets the message on the most recently updated open lead. A known contact with only closed leads gets a new lead. Won and lost leads are not reopened.
- Attachment metadata (id, mime type, caption) is stored. Media files are not downloaded.
- The whole inbound text `stop` or `unsubscribe` (any case) sets the contact to opted out. No other wording does.
- Status webhooks (`sent`, `delivered`, `read`, `failed`) update the outbound row and append `message_status_events`. A send request is never treated as delivered.
- Operators send free-form text from the lead. The local row is created as `queued` with an idempotency key, then the Cloud API is called. The provider message id and raw response are stored.
- Network failures, HTTP 429, and HTTP 5xx can retry that same row. A timeout that might have reached WhatsApp becomes `unknown` and is not sent again.
- Opted-out contacts cannot be sent to. Outside the 24-hour window after the last inbound WhatsApp message, free-form text is refused and a template would be required. There is no template catalog. Refusals are stored and are not left queued.
- Pause automation, resume, take over, and needs personal attention are lead flags. A desk-wide switch pauses automatic sends. Those checks run in the messaging service. Manual sends still work during takeover, pause, and the global pause.

Point Meta at `https://<public-host>/webhooks/whatsapp` and subscribe to the `messages` field. The verify token is `WHATSAPP_VERIFY_TOKEN`.

Required environment variables, all empty in `.env.example`:

- `WHATSAPP_TOKEN`
- `WHATSAPP_PHONE_NUMBER_ID`
- `WHATSAPP_APP_SECRET`
- `WHATSAPP_VERIFY_TOKEN`

The WhatsApp page shows configured or missing for each name. It does not display the values.

For a public webhook, run a queue worker so the HTTP handler can return before the job finishes:

```bash
php artisan queue:work
```

`QUEUE_CONNECTION=sync` still processes the job before the response, which is what the test suite uses. `php artisan whatsapp:retry` replays stored events and queued sends if Redis was down when the row was saved.

## Phase 3

A replaceable language model (OpenAI or Gemini via `LLM_PROVIDER`) reads one lead. Turn on **Live AI assistant** under WhatsApp to answer inbound webhooks with product-aware replies that keep a running conversation topic. When the live assistant is off, owner rules on Intelligence still apply — no matching rule means silence.

## Calendar

The Laundry calendar (`/calendar`) lets you pick days, filter the month, and assign blue/white activities. Activate **Google Calendar** on `/integrations` (set `GOOGLE_CALENDAR_ID` and `GOOGLE_CALENDAR_ACCESS_TOKEN`). When activated, new desk activities and marked lead dates push to Google; `external_calendar_id` stores the remote event id.

## Automated sales agent controls

Each lead keeps its pipeline stage while you speak. On the lead page you can:

- **Record touch** — phone call, meeting, visit, or note with date/time (backdating allowed) and optional duration/outcome. The live assistant and analysis read these.
- **Schedule conversation** — set `next_conversation_at` so the agent knows when to talk again.
- **Stop future conversations** — pauses automation and cancels pending follow-up tasks. **Allow conversations** turns the agent back on.

Command centre stage buckets surface follow-up stages, almost-buying, meetings/demos, and touches recorded today.

## Desk colours

The UI follows Laundry OS: **white + blue** (`#1d4ed8`) as the base brand. Special-effect tones: **green** (won / paid / upcoming), **red** (lost / overdue), and **lead slate** (neutral lead counts and void states). Appearance defaults to light.

## Billing

Invoices (`/invoices`) support logo + address on the business profile, PDF download, a public share link, paid/unpaid/overdue status, monthly customers with due-date alerts, and a tax log (`/billing/tax-log`) that records tax when an invoice is marked paid.

## Expenses

`/expenses` logs spend by category with receipts and monthly stats. Expense tax writes into the same tax log under `system_name` (default `expenses`).

## Reports

`/reports` is the desk analytics board. Pick a date range (or 7d / 30d / 90d) to see leads per product, conversion per product, high-interest leads per product (Demo / Evaluating / Proposal / Closing or AI score ≥ 70), daily lead/won charts, and spend every day plus category totals.

## Money

`/money` tracks payment accounts (multiple banks, M-Pesa, cash), people you owe / who owe you, and recurring monthly inflows/outflows. Mark a recurring item paid to roll its next due date.

## Payment methods & subscription payouts

Settings → **Payment methods** defines how clients pay (M-Pesa, bank transfer, cash, card, cheque, plus any custom method). On **Monthly customers** and on a contact with an active subscription, record a payout with **paid on** date and **method**. That advances the next due date, stores the last payment, and creates a paid invoice. Marking an invoice paid also asks for paid-on date and method.

## WhatsApp knowledge pull

On Intelligence, **Pull WhatsApp knowledge** syncs Meta WhatsApp Business profile fields (when Meta credentials exist) and mines frequent inbound questions from desk conversations into knowledge notes.

## Integrations

`/integrations` connects Meta Business page insights and Google Search Console search analytics. Set `META_BUSINESS_TOKEN` / `META_PAGE_ID` and `GSC_SITE_URL` / `GSC_ACCESS_TOKEN`, then sync for advanced report tables.

- `LLM_API_KEY`, `LLM_BASE_URL`, and `LLM_MODEL` must all be set. The Intelligence page shows configured or missing for each name and never shows the values. If any one is missing, a read is stored as unavailable. No summary or score is invented.
- `LLM_BASE_URL` is an OpenAI-compatible root such as `https://api.openai.com/v1`. The desk posts to `{base}/chat/completions`.
- "Read the conversation" stores a `lead_analyses` row, then asks the model for JSON: summary, extracted lead fields, a score from 0 to 100 with reasons, a suggested stage, and a reply draft.
- Knowledge notes on the Intelligence page are retrieved by shared words. A note tied to the lead's product is boosted. Inactive notes are skipped. The article ids used are stored on the analysis.
- A suggested stage is kept only when it is a permitted move from the current stage. Anything else is dropped and the reason is shown. The lead stays put until someone applies the suggestion, which goes through `SalesStageTransitionService`.
- Extracted fields are applied only when an operator selects them.
- The reply draft can be edited. The original model wording is kept. "Approve and send" uses the existing manual WhatsApp send, including opt-out and the 24-hour window. Saving the draft does not send.
- If the model request fails, the conversation messages stay and the analysis is marked failed.
- If the queue push fails, the row stays `queued`. `php artisan ai:retry` runs it.

## Phase 4

Follow-up rules schedule tasks. `php artisan follow-ups:run` creates them and sends the WhatsApp ones that are due. The scheduler calls that command every minute. Start it with `php artisan schedule:work`, or cron `php artisan schedule:run`. The Follow-ups page does not pretend the scheduler is already running.

- An owner saves rules. A sales user can see them and can cancel or complete a task. The hours and the Nairobi time live on the rule or on the stage. They are not hardcoded in the runner.
- No response: after the last outbound WhatsApp message, wait the rule's hours. A later outbound replaces the pending task. A WhatsApp reply on that lead cancels it before it sends.
- Follow-up date: the lead's `next_follow_up_on` at the rule's Nairobi time. Changing the date cancels the previous pending task and schedules the new one.
- Stage delay: the hours stored on the current stage, measured from the stage change. A reply cancels it. A stage with no delay is skipped.
- WhatsApp tasks go through the existing automatic-send checks. Opt-out, a closed 24-hour window, and a missing token are refused once and stored. Pause, takeover, and personal attention hold the task so it can still send after they are cleared. The same task id is the idempotency key, so a second pass does not send it again.
- Desk tasks stay pending until someone marks them done. They do not send.
- Turning a rule off or closing the lead cancels its pending tasks.

Message text may include `{contact}`, `{business}`, and `{action}`.

## Not built yet

These are intentionally absent.

- Template sending
- Downloading WhatsApp media
- Full Google Calendar two-way sync (one-way push when activated; no Google → desk import yet)
- Reply learning from edits (the original draft and the edited text are stored; nothing trains on them)
- Analytics beyond the command-centre counts and the score on a lead
- Production VPS hardening

## Run locally

Requirements: PHP 8.3+, Composer, Node 22+, Docker.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set SEED_USER_PASSWORD in .env before seeding the owner.
npm install
docker compose up -d
php artisan migrate --force
php artisan db:seed
php artisan db:seed --class=DemoDataSeeder
```

`db:seed` creates the owner and the "Kemet Tech sales" pipeline. It does not load sample prospects.

`DemoDataSeeder` loads a handful of fictional Kenyan B2B prospects, including one opted-out contact. It is sample data. Do not run it against a production database.

Two processes:

```bash
php artisan serve --host=0.0.0.0 --port=43123
npm run dev
```

Vite is configured for port 43124. Open the desk at the `APP_URL`.

PostgreSQL and Redis come from Docker Compose. Phase 1 keeps the queue synchronous and the cache in the database. Redis is there for a later phase.

Password reset links are written to `storage/logs/laravel.log` with the log mailer.

## Tests

```bash
composer test
```

That runs Pint, PHPStan, and Pest. Feature tests use SQLite in memory. The schema itself is written for PostgreSQL (foreign keys, a partial unique index for open leads, JSON columns).

## Deployment

Not yet. This repository is the sales desk, not a production deploy guide.
