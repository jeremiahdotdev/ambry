# Ambry

Ambry is a monorepo for the Catholic saints discovery product. It contains the Laravel web app that owns the user experience and database migrations, plus a distinct Go REST API app for read-only developer access to the same saints database.

## Apps

- `apps/web`: Laravel web app, developer dashboard, API key management, saint search, profile pages, imports, and database migrations.
- `apps/api`: Go REST API with Huma/OpenAPI docs, Vercel serverless entry point, local server entry point, and read-only PostgreSQL repositories.

The Laravel app owns database topology. The Go API consumes the existing schema and may keep SQL/schema snapshots for local development, but it should not introduce or run production migrations.

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

## Go API Development

```bash
cd apps/api
go run ./cmd/api
```

Useful endpoints:

- `GET /health`
- `GET /docs`
- `GET /openapi.json`
- `GET /api/v1/saints`
- `GET /api/v1/saints/{slug}`
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

The Go API has its own `apps/api/vercel.json`. Deploy it as a separate Vercel project with the project root set to `apps/api`, or use its Dockerfile on a conventional host.

Both apps should point at the same production database, but only the Laravel app should run migrations.
