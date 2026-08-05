package api

import (
	"log/slog"
	"net/http"

	"api/internal/auth"
	"api/internal/bibleverse"
	"api/internal/config"
	"api/internal/database"
	"api/internal/feastday"
	"api/internal/patronage"
	"api/internal/religiousorder"
	"api/internal/saint"
	"github.com/danielgtaylor/huma/v2"
	"github.com/danielgtaylor/huma/v2/adapters/humago"
)

type Server struct {
	Handler http.Handler
	API     huma.API
}

type ServerOptions struct {
	Config          config.Config
	Logger          *slog.Logger
	Health          database.HealthChecker
	Saints          saint.Service
	Patronages      patronage.Service
	ReligiousOrders religiousorder.Service
	FeastDays       feastday.Service
	BibleVerses     bibleverse.Service
	Authenticator   auth.Authenticator
}

const apiOverview = `Read-only API for Ambry's Catholic saints, patronages, religious orders, feast days, and Bible verses.

## Getting Started

1. Create an account in the web app at /signup, or log in at /login.
2. Open /developers/api-keys.
3. Create a key with a descriptive name and optional expiration date.
4. Copy the token immediately. The full token is shown only once.

Each email can have up to 3 active API keys. Expired or revoked keys do not count against that limit.

## Authentication

All /api/v1/* endpoints require a developer API key. Send it as a bearer token:

` + "```bash" + `
curl -H 'Authorization: Bearer saints_test_...' \
  '/api/v1/saints?q=patrick'
` + "```" + `

You can also send it with X-API-Key:

` + "```bash" + `
curl -H 'X-API-Key: saints_test_...' \
  '/api/v1/bible-verses?book_code=gen&chapter=1'
` + "```" + `

API keys are rate limited to 10 requests per second per key. Requests over the limit return 429 Too Many Requests with Retry-After: 1.

Public routes are limited to /health, /, /openapi.json, and /openapi.yaml.`

const docsCustomCSS = `
:root {
  --scalar-font: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --scalar-font-code: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
}

.light-mode {
  --scalar-color-1: #20252d;
  --scalar-color-2: #4f5b6b;
  --scalar-color-3: #667085;
  --scalar-color-accent: #8b3f1d;
  --scalar-background-1: #fff9ef;
  --scalar-background-2: #f7efe4;
  --scalar-background-3: #f5e7d6;
  --scalar-background-accent: rgb(216 160 82 / 16%);
  --scalar-border-color: #ded3c1;
}

.light-mode .sidebar {
  --scalar-sidebar-background-1: #42183d;
  --scalar-sidebar-item-hover-color: #f8e9c8;
  --scalar-sidebar-item-hover-background: rgb(248 233 200 / 10%);
  --scalar-sidebar-item-active-background: rgb(216 160 82 / 18%);
  --scalar-sidebar-border-color: rgb(248 233 200 / 16%);
  --scalar-sidebar-color-1: #f8e9c8;
  --scalar-sidebar-color-2: rgb(248 233 200 / 72%);
  --scalar-sidebar-color-active: #f5c471;
  --scalar-sidebar-search-background: rgb(248 233 200 / 10%);
  --scalar-sidebar-search-border-color: rgb(248 233 200 / 18%);
  --scalar-sidebar-search-color: rgb(248 233 200 / 78%);
}

.light-mode button,
.light-mode .scalar-button {
  border-radius: 6px;
}
`

func NewServer(opts ServerOptions) Server {
	mux := http.NewServeMux()
	humaConfig := huma.DefaultConfig("✣ Ambry API", "1.0.0")
	humaConfig.Info.Description = apiOverview
	humaConfig.DocsPath = "/"
	humaConfig.DocsRenderer = huma.DocsRendererScalar
	humaConfig.DocsRendererConfig = map[string]any{
		"theme":              "none",
		"layout":             "modern",
		"forceThemeMode":     "light",
		"hideDarkModeToggle": true,
		"hideModels":         false,
		"withDefaultFonts":   false,
		"customCss":          docsCustomCSS,
	}
	humaConfig.Components.SecuritySchemes = map[string]*huma.SecurityScheme{
		"BearerAuth": {
			Type:         "http",
			Scheme:       "bearer",
			BearerFormat: "Developer API key",
			Description:  "Use the developer API key created at /developers/api-keys.",
		},
	}
	humaConfig.Servers = []*huma.Server{{URL: "/"}}
	api := humago.New(mux, humaConfig)

	RegisterRoutes(api, opts)

	return Server{
		Handler: middlewareChain(mux, opts.Config, opts.Logger, opts.Authenticator),
		API:     api,
	}
}
