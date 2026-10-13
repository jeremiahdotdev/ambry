# Ambry

Ambry is a monorepo for the Catholic saints discovery product. It contains the Laravel web app that owns the user experience and database migrations, plus a distinct Go REST API app for developer access to the same saints database and persisted saint-of-the-day selections.

## Apps

- `apps/web`: Laravel web app, developer dashboard, API key management, saint search, profile pages, imports, and database migrations.
- `apps/api`: Go REST API with Huma/OpenAPI docs, Vercel serverless entry point, local server entry point, and PostgreSQL repositories.

The Laravel app owns the core database topology. The Go API consumes that schema and owns the additional `saint_of_the_day` selection history table. Its SQL migration is applied explicitly, never automatically by the API; see [Go API setup and saint-of-the-day behavior](apps/api/README.md#saint-of-the-day).

## Requirements

Web app:

- PHP 8.4+
- Composer
- Node.js and npm
- SQLite

Go API:

- Go 1.24+
- PostgreSQL connection string via `DATABASE_URL`

## Web Setup

```bash
cd apps/web
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

The default local database path is `apps/web/database/database.sqlite`.

## Web Development

```bash
pnpm dev
```

From `apps/web`, you can also run the Laravel app directly:

```bash
cd apps/web
php artisan serve
npm run dev
```

## Web Frontend

The web app uses Livewire 4 with Blade templates. Interactive components live in
`apps/web/app/Livewire` and `apps/web/resources/views/livewire`: search and suggestions,
results pagination, saint editing, editor staff, API keys, login, and signup.
Internal links use `wire:navigate`; Alpine handles dropdowns and keyboard interactions.

The shared JavaScript entry bundles Livewire and its Alpine instance through Vite.
Layouts use `@livewireScriptConfig`; do not load a second copy of Alpine or Livewire.
Controllers still serve the page shells and existing HTTP endpoints, while shared
services handle search, permissions, saint updates, and API key creation.

## Go API Development

```bash
cd apps/api
go run ./cmd/api
```

Useful endpoints:

- `GET /health`
- `GET /`
- `GET /openapi.json`
- `GET /api/v1/saints`
- `GET /api/v1/saints/{slug}`
- `GET /api/v1/saint-of-the-day` (optional `date=YYYY-MM-DD`, defaults to today in UTC; no repeated saints within a year)
- `GET /api/v1/patronages`
- `GET /api/v1/religious-orders`
- `GET /api/v1/feast-days`

Developer API routes require an API key created in the Laravel developer dashboard. Send it as either:

```bash
curl -H 'Authorization: Bearer saints_test_...' 'http://localhost:8080/api/v1/saints'
curl -H 'X-API-Key: saints_test_...' 'http://localhost:8080/api/v1/saints'
```

The Go app stores no raw keys. It hashes incoming tokens and checks the Laravel-owned `developer_api_keys` table for active, unexpired, unrevoked keys.

## Tests

```bash
pnpm test
pnpm run build
```

The root `pnpm` commands run against `apps/web`. From inside `apps/web`, use `php artisan test` and `npm run build`.

For the Go API:

```bash
cd apps/api
go test ./...
go build -o bin/ambry-api ./cmd/api
```

## Data Scripts

Database cleanup scripts live in `apps/web/database/scripts`.

```bash
php apps/web/database/scripts/normalize_life_dates.php
```

That script normalizes stored `life_dates` values by appending `AD` to dated strings that do not already specify an era.

## Deployment

The web app keeps its `vercel.json` in `apps/web`. Deploy it as a Vercel project with the project root set to `apps/web`.

The frontend bundle does not import Composer dependencies. Laravel serves Livewire and Alpine through `@livewireScripts` at runtime, and `resources/js/app.js` registers components on `livewire:init`. This lets Vite build before the PHP runtime installs Composer packages.

The Go API has its own `apps/api/vercel.json`. Deploy it as a separate Vercel project with the project root set to `apps/api`, or use its Dockerfile on a conventional host.

Both apps should point at the same production database. The Laravel app runs core migrations; the Go-owned saint-of-the-day SQL migration must also be applied explicitly before deploying that endpoint.

## Saved saint avatars

`saints.image_avatar_url` is separate from the original cutout, portrait, and thumbnail URLs.
The Python backfill uses the same thumbnail/fallback as the search list, retaining its full
width and top 55% (the desktop CSS clips a 20rem image to 11rem). It preserves transparency
and aspect ratio and limits the PNG to 512px per edge. Mobile results clip this same avatar
further to 9.4rem. Profiles continue using the original portrait; API detail, search, and
saint-of-the-day responses expose `image_avatar_url` when populated.

From the repository root:

```bash
cd apps/web
php artisan migrate --path=database/migrations/2026_10_13_000001_add_image_avatar_url_to_saints_table.php
python3 -m venv /tmp/ambry-avatars-venv
/tmp/ambry-avatars-venv/bin/pip install -r database/scripts/requirements-saint-avatars.txt
/tmp/ambry-avatars-venv/bin/python database/scripts/backfill_saint_avatars.py --limit 5 --preview-dir /tmp/saint-avatar-previews
/tmp/ambry-avatars-venv/bin/python database/scripts/backfill_saint_avatars.py --apply
```

The script loads `apps/web/.env` without overriding exported variables. It uses `DB_URL`
or `DATABASE_URL` and needs `BLOB_READ_WRITE_TOKEN` for `--apply`. Use `--env-file` for
another environment, or `--database` for a PostgreSQL URL or existing SQLite file.
The default is a dry run: it reads and crops, but does not upload or update the DB.
`--slug` selects one saint; `--limit` caps the run; existing avatars are skipped unless
`--force` is supplied. Failures are reported per saint and produce a nonzero exit status;
rerun to resume. Each successful upload commits only `image_avatar_url`, with a guard
against concurrent edits. Original image files, URLs, and other saint fields are unchanged.
Uploaded PNGs use a separate content-addressed `saints/<id>/avatars/` path.
The [Vercel Blob upload implementation](https://github.com/vercel/storage/blob/main/packages/blob/src/put.ts)
is the reference for the Python uploader's HTTP format. No OpenAI calls are made.

Local script tests (no database services or Blob credentials required):

```bash
/tmp/ambry-avatars-venv/bin/python -m unittest discover -s database/scripts -p 'test_saint_avatars.py'
```
