# Ambry API

Read-only Go REST API app for the existing Ambry Catholic saints PostgreSQL database. This app lives inside the Ambry monorepo at `apps/api`.

## Overview

Ambry exposes a read-only REST API for saints, patronages, religious orders, feast days, and Bible verses. The interactive API documentation is available at `/`, and the raw OpenAPI documents are available at `/openapi.json` and `/openapi.yaml`.

All `/api/v1/*` endpoints require a developer API key. Public endpoints are limited to `/health`, `/`, `/openapi.json`, `/openapi.yaml`, and `/schemas/*`.

### Get an API Key

1. Create an account at `/signup`, or log in at `/login` if you already have one.
2. Open `/developers/api-keys`.
3. Create a key with a descriptive name and optional expiration date.
4. Copy the token immediately. The full token is shown only once.

You can create multiple keys for different integrations. Requests from all keys on the same account share the same account-level quota.

### Authenticate Requests

Send the API key as a bearer token:

```bash
curl -H 'Authorization: Bearer saints_test_...' \
  'http://localhost:8080/api/v1/saints?q=patrick'
```

You can also send it with `X-API-Key`:

```bash
curl -H 'X-API-Key: saints_test_...' \
  'http://localhost:8080/api/v1/bible-verses?book_code=gen&chapter=1'
```

API access is rate limited per user account with Upstash Redis: 60 requests per minute and 5,000 requests per day across all keys on the account. Requests over the limit return `429 Too Many Requests` with `Retry-After: 60`. Configure `UPSTASH_REDIS_REST_URL` and `UPSTASH_REDIS_REST_TOKEN`; Vercel KV-style `KV_REST_API_URL` and `KV_REST_API_TOKEN` are also supported.

## Stack

- Go 1.24+
- Standard `net/http`
- Huma v2 with `humago` for routing, validation, OpenAPI, and docs at `/`
- `pgx/v5` connection pooling
- Upstash Redis REST API for account-level API quotas
- SQL query files and `sqlc.yaml` for sqlc generation
- `log/slog` structured logging
- Docker and Docker Compose for local development

## Project Layout

- `cmd/api`: local long-running server entry point
- `api`: Vercel serverless entry point
- `internal/api`: shared HTTP server assembly, middleware, health route, and route composition
- `internal/saint`, `internal/patronage`, `internal/religiousorder`, `internal/feastday`: resource models, repositories, services, and Huma route handlers
- `internal/app`: platform-neutral application construction
- `internal/config` and `internal/database`: configuration and PostgreSQL plumbing

## Discovered Schema

The API was mapped from the existing Laravel migrations in `apps/web` and does not modify the database topology.

Core tables used:

- `saints`
- `saint_aliases`
- `feast_days`
- `patronages`
- `patronage_saint`
- `religious_orders`
- `religious_order_saint`

Additional profile fields used when available:

- `saints.profile_summary` for list summaries
- `saints.profile_landmarks` for detail `locations`
- `saints.profile_church_roles` or `saints.roles` for detail `titles`
- `developer_api_keys` for API key authentication

Unresolved mappings:

- There are no dedicated `locations` or `titles` tables in the discovered schema. The API maps these from JSON profile fields instead.
- The schema snapshot in `db/schema/schema.sql` is for local/sqlc development only. It is not a production migration.

## Endpoints

- `GET /health`
- `GET /api/v1/saints`
- `GET /api/v1/saints/{slug}`
- `GET /api/v1/patronages`
- `GET /api/v1/religious-orders`
- `GET /api/v1/feast-days`
- `GET /api/v1/bible-verses`

Documentation:

- `GET /`
- `GET /openapi.json`
- `GET /openapi.yaml`

All `/api/v1/*` routes require a developer API key created by the Laravel web app.

## Configuration

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Environment variables:

- `DATABASE_URL`: required PostgreSQL connection string
- `PORT`: defaults to `8080`
- `APP_ENV`: defaults to `development`
- `LOG_LEVEL`: `debug`, `info`, `warn`, or `error`
- `ALLOWED_ORIGINS`: comma-separated CORS origins or `*`
- `DATABASE_MAX_CONNECTIONS`: defaults to `3`
- `DATABASE_MIN_CONNECTIONS`: defaults to `0`
- `DATABASE_MAX_CONN_LIFETIME`: defaults to `30m`
- `DATABASE_MAX_CONN_IDLE_TIME`: defaults to `5m`
- `DATABASE_CONNECT_TIMEOUT`: defaults to `5s`
- `QUERY_TIMEOUT`: defaults to `5s`
- `REQUEST_TIMEOUT`: defaults to `10s`

## Running Locally

```bash
go run ./cmd/api
```

`cmd/api/main.go` is the conventional long-running server entry point. It loads configuration, builds the shared HTTP handler, starts `http.Server`, and performs graceful shutdown. Route registration is not duplicated there.

Example requests:

```bash
curl 'http://localhost:8080/health'
curl -H 'Authorization: Bearer saints_test_...' 'http://localhost:8080/api/v1/saints?q=patrick'
curl -H 'Authorization: Bearer saints_test_...' 'http://localhost:8080/api/v1/saints/st-patrick'
curl -H 'X-API-Key: saints_test_...' 'http://localhost:8080/api/v1/patronages?q=ireland'
curl -H 'X-API-Key: saints_test_...' 'http://localhost:8080/api/v1/religious-orders'
curl -H 'X-API-Key: saints_test_...' 'http://localhost:8080/api/v1/feast-days?month=3&day=17'
curl -H 'X-API-Key: saints_test_...' 'http://localhost:8080/api/v1/bible-verses?book_code=gen&chapter=1'
```

### Bible Book Codes

The `/api/v1/bible-verses` endpoint accepts either the exact `book` name or the exact `book_code`.

| Order | Code | Book |
| ---: | --- | --- |
| 1 | `gen` | Genesis |
| 2 | `exo` | Exodus |
| 3 | `lev` | Leviticus |
| 4 | `num` | Numbers |
| 5 | `deu` | Deuteronomy |
| 6 | `jos` | Joshua |
| 7 | `jdg` | Judges |
| 8 | `rut` | Ruth |
| 9 | `1sa` | 1 Samuel |
| 10 | `2sa` | 2 Samuel |
| 11 | `1ki` | 1 Kings |
| 12 | `2ki` | 2 Kings |
| 13 | `1ch` | 1 Chronicles |
| 14 | `2ch` | 2 Chronicles |
| 15 | `ezr` | Ezra |
| 16 | `neh` | Nehemiah |
| 17 | `tob` | Tobit |
| 18 | `jth` | Judith |
| 19 | `est` | Esther |
| 20 | `job` | Job |
| 21 | `psa` | Psalms |
| 22 | `pro` | Proverbs |
| 23 | `ecc` | Ecclesiastes |
| 24 | `son` | Song of Songs |
| 25 | `wis` | Wisdom |
| 26 | `sir` | Sirach |
| 27 | `isa` | Isaiah |
| 28 | `jer` | Jeremiah |
| 29 | `lam` | Lamentations |
| 30 | `bar` | Baruch |
| 31 | `eze` | Ezekiel |
| 32 | `dan` | Daniel |
| 33 | `hos` | Hosea |
| 34 | `joe` | Joel |
| 35 | `amo` | Amos |
| 36 | `oba` | Obadiah |
| 37 | `jon` | Jonah |
| 38 | `mic` | Micah |
| 39 | `nah` | Nahum |
| 40 | `hab` | Habakkuk |
| 41 | `zep` | Zephaniah |
| 42 | `hag` | Haggai |
| 43 | `zec` | Zechariah |
| 44 | `mal` | Malachi |
| 45 | `1ma` | 1 Maccabees |
| 46 | `2ma` | 2 Maccabees |
| 47 | `mat` | Matthew |
| 48 | `mar` | Mark |
| 49 | `luk` | Luke |
| 50 | `joh` | John |
| 51 | `act` | Acts |
| 52 | `rom` | Romans |
| 53 | `1co` | 1 Corinthians |
| 54 | `2co` | 2 Corinthians |
| 55 | `gal` | Galatians |
| 56 | `eph` | Ephesians |
| 57 | `phi` | Philippians |
| 58 | `col` | Colossians |
| 59 | `1th` | 1 Thessalonians |
| 60 | `2th` | 2 Thessalonians |
| 61 | `1ti` | 1 Timothy |
| 62 | `2ti` | 2 Timothy |
| 63 | `tit` | Titus |
| 64 | `phm` | Philemon |
| 65 | `heb` | Hebrews |
| 66 | `jam` | James |
| 67 | `1pe` | 1 Peter |
| 68 | `2pe` | 2 Peter |
| 69 | `1jo` | 1 John |
| 70 | `2jo` | 2 John |
| 71 | `3jo` | 3 John |
| 72 | `jud` | Jude |
| 73 | `rev` | Revelation |

## Docker

```bash
cp .env.example .env
make docker-up
```

The Compose PostgreSQL service is for local development. It starts with the schema snapshot only, not production data.

## Vercel Deployment

Vercel-specific code is limited to:

- `api/index.go`
- `vercel.json`

The Vercel handler exports:

```go
func Handler(w http.ResponseWriter, r *http.Request)
```

It lazily initializes the shared application handler once per warm function instance and reuses the PostgreSQL pool across requests handled by that instance. It never calls `ListenAndServe`.

`vercel.json` rewrites only the public API and documentation paths to the Go function at `/api/index` and passes the original public route in an internal `route` query parameter. The Vercel handler restores `r.URL.Path` before handing the request to Huma, so the shared routes still see paths such as:

- `/health`
- `/api/v1/saints`
- `/api/v1/saints/st-patrick`
- `/api/v1/patronages`
- `/api/v1/religious-orders`
- `/api/v1/feast-days`
- `/`
- `/openapi.json`
- `/openapi.yaml`

Deployment steps:

1. Import this repository into Vercel.
2. Set the Vercel project root directory to `apps/api`.
3. Let Vercel detect the Go serverless function in `api/index.go`.
4. Add `DATABASE_URL` as a project environment variable.
5. Prefer a pooled Neon PostgreSQL connection string for `DATABASE_URL`.
6. Set `ALLOWED_ORIGINS` to your production frontend origin and any local development origins, comma-separated.
7. Keep serverless pool settings small, for example `DATABASE_MAX_CONNECTIONS=3` and `DATABASE_MIN_CONNECTIONS=0`.
8. Deploy.
9. Verify `https://your-domain/health`.
10. Open `https://your-domain/`.
11. Open `https://your-domain/openapi.json`.

The API does not run migrations during Vercel request execution. Run database migrations separately through the owning Laravel app, a CI job, or an administrative migration command pointed at the intended database.

The shared handler is platform-neutral. To move later to Railway, Render, Fly.io, a VPS, Docker, or another conventional host, use `cmd/api/main.go` or a container image without rewriting route registration.

## SQLC

SQL files live in `db/query`. The schema snapshot lives in `db/schema/schema.sql`.

```bash
make sqlc
```

The repository currently uses hand-written pgx queries that mirror these SQL files so the project structure is ready for sqlc generation once `sqlc` is installed.

## Tests and Verification

```bash
go fmt ./...
go vet ./...
go test ./...
go build -o bin/ambry-api ./cmd/api
```

Integration tests should be run against the Docker Compose PostgreSQL database, never the production database.

Vercel build verification, when the Vercel CLI is available:

```bash
vercel build
```

## Search Ranking

The initial implementation uses safe case-insensitive matching with `lower(...) like $n` across primary names, aliases, biography, patronages, and religious orders. The repository is structured so PostgreSQL full-text search or trigram indexes can be added later without changing handler contracts.

Current sort options:

- `name`
- `birth_year`
- `death_year`
- `feast_day`
