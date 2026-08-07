package server

import (
	"log/slog"
	"net/http"
	"strings"

	"api/internal/auth"
	"api/internal/config"
	"api/internal/database"
	"api/internal/docs"
	"api/internal/middleware"
	"api/internal/repos/bibleverse"
	"api/internal/repos/feastday"
	"api/internal/repos/patronage"
	"api/internal/repos/religiousorder"
	"api/internal/repos/saint"
	"api/internal/routes"
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

const bearerAuthDescription = "Use the developer API key created at /developers/api-keys."

func NewServer(opts ServerOptions) Server {
	mux := http.NewServeMux()
	humaConfig := huma.DefaultConfig("✣ Ambry API", "1.0.0")
	humaConfig.Info.Description = strings.TrimSpace(docs.OverviewMarkdown)
	humaConfig.Tags = []*huma.Tag{
		{Name: "Bible Verses", Description: "Search and filter Bible verses by book, chapter, verse, and text."},
		{Name: "Feast Days", Description: "Find saints associated with feast-day calendar dates."},
		{Name: "Patronages", Description: "Browse patronage categories and their associated saint counts."},
		{Name: "Religious Orders", Description: "Browse religious orders and their associated saint counts."},
		{Name: "Saints", Description: "Search saint records and retrieve full saint profiles."},
		{Name: "Health", Description: "Check service and database availability."},
	}
	humaConfig.DocsPath = "/"
	humaConfig.DocsRenderer = huma.DocsRendererScalar
	humaConfig.DocsRendererConfig = map[string]any{
		"theme":              "none",
		"layout":             "modern",
		"darkMode":           false,
		"hideDarkModeToggle": false,
		"hideModels":         false,
		"withDefaultFonts":   false,
		"customCss":          docs.CustomCSS,
	}
	humaConfig.Components.SecuritySchemes = map[string]*huma.SecurityScheme{
		"BearerAuth": {
			Type:         "http",
			Scheme:       "bearer",
			BearerFormat: "Developer API key",
			Description:  bearerAuthDescription,
		},
	}
	humaConfig.Servers = []*huma.Server{{URL: "/"}}
	api := humago.New(mux, humaConfig)

	routes.RegisterRoutes(api, routes.Options{
		Logger:          opts.Logger,
		Health:          opts.Health,
		Saints:          opts.Saints,
		Patronages:      opts.Patronages,
		ReligiousOrders: opts.ReligiousOrders,
		FeastDays:       opts.FeastDays,
		BibleVerses:     opts.BibleVerses,
	})

	return Server{
		Handler: middleware.Chain(mux, opts.Config, opts.Logger, opts.Authenticator),
		API:     api,
	}
}
