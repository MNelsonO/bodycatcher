# Kemet Tech sales desk

Personal sales desk for Kemet Tech. Phase 1 is the foundation and CRM: sign in, products, pipelines, contacts, leads, manual message logging, and a command centre that counts what is actually in the database.

Nelson Mboya is the first operator. The seeded owner is created from `SEED_USER_EMAIL` and `SEED_USER_PASSWORD`.

## Phase 1

- Login, logout, and password reset (the reset link is written to the log when `MAIL_MAILER=log`).
- Products with a name, slug, description, active flag, and optional pricing notes. No prices are hardcoded and there is no checkout.
- Pipelines, stages (active, won, lost, archived-nurture), reorder, archive, and permitted transitions. A stage can store a follow-up delay in hours. Nothing executes that delay yet.
- Contacts with E.164 phones. Local Kenyan numbers default to +254. The same number updates the existing contact. The same name does not.
- Manual merge keeps leads, notes, and message history on the surviving contact and writes a merge audit row.
- Leads tied to a contact, product, and pipeline stage. One open lead per contact and product. A closed lead does not block a new one.
- Stage changes go through `SalesStageTransitionService`, inside a database transaction, with history and the actor. Lost stages require a reason. Illegal moves are rejected and do not write history.
- Messages and notes are ordinary database writes. AI is not involved.
- An activity log on the lead profile.
- A desktop-first command centre: total leads, new leads today, follow-ups due today, overdue follow-ups, leads by stage, won and lost. Dates display in Africa/Nairobi. Timestamps are stored in UTC.

## Not in this phase

These are intentionally absent. There are no buttons that pretend they exist.

- WhatsApp Cloud API sending or inbound webhooks
- LLM analysis, scoring, or reply suggestions
- An automation engine that fires follow-ups
- Google Calendar
- Reply learning
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

Not yet. This repository is the Phase 1 desk, not a production deploy guide.
