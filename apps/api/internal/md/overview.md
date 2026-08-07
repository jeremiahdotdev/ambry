Ambry exposes a read-only REST API for Catholic saints, patronages, religious orders, feast days, and Bible verses.

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
