Ambry exposes a REST API for Catholic saints, patronages, religious orders, feast days, and Bible verses.

## Base URL and Docs

Use the same host that serves this documentation. The interactive docs are available at /, and the raw OpenAPI documents are available at /openapi.json and /openapi.yaml.

## Getting Started

1. Create an account in the web app at /signup, or log in at /login.
2. Open /developers/api-keys.
3. Create a key with a descriptive name and optional expiration date.
4. Copy the token immediately. The full token is shown only once.

You can create multiple keys for different integrations. Requests from all keys on the same account share the same account-level quota.

## Authentication

All /api/v1/* endpoints require a developer API key. In this docs UI, use the authentication control and paste the token as the bearer value.

For direct HTTP requests, send the key as a bearer token:

```bash
curl -H 'Authorization: Bearer saints_test_...' \
  '/api/v1/saints?q=patrick'
```

You can also send it with X-API-Key:

```bash
curl -H 'X-API-Key: saints_test_...' \
  '/api/v1/bible-verses?book_code=gen&chapter=1'
```

API access is rate limited per user account with Upstash Redis: 60 requests per fixed UTC minute and 5,000 requests per UTC day across all keys on the account. Requests over the limit return 429 Too Many Requests with Retry-After: 60.

Public routes are limited to /health, /, /openapi.json, /openapi.yaml, and /schemas/*.


## Saint of the Day

GET /api/v1/saint-of-the-day returns a saved daily selection as a date and a full saint profile. Pass date=YYYY-MM-DD for a specific date, or omit it for today in UTC. Invalid dates return 400.

The first request persists a selection shared by all users and API keys. Later requests for that date return the same saint with its current profile. Only records with canonical_status = 'saint' are eligible. Selection prefers an unused saint with a matching feast day across any calendar, then randomly falls back to another unused saint. No saint repeats within the same calendar year, even when requests run concurrently. Saints become eligible again in a new year.

Past and future dates reserve selections immediately. Dates with no requests are not filled automatically. When no unused saints remain, new dates return 409 Conflict; saved selections remain available. There must be at least 365 eligible saints to fill an entire year, or 366 in a leap year.

Before using this endpoint, apply the Go-owned db/migrations/001_saint_of_the_day.sql migration and give the API database role SELECT and INSERT permissions on saint_of_the_day. The API never applies migrations automatically.
