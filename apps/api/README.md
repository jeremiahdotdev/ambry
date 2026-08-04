# Ambry API

Read-only Go REST API app for the existing Ambry Catholic saints PostgreSQL database. This app lives inside the Ambry monorepo at `apps/api`.

## Stack

- Go 1.24+
- Standard `net/http`
- Huma v2 with `humago` for routing, validation, OpenAPI, and docs at `/`
- `pgx/v5` connection pooling
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

Documentation:

- `GET /`
- `GET /openapi.json`
- `GET /openapi.yaml`

All `/api/v1/*` routes require a developer API key created by the Laravel web app. Public routes are limited to `/health`, `/`, `/openapi.json`, and `/openapi.yaml`.

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
```

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
