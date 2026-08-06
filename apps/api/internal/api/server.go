package api

import (
	"log/slog"
	"net/http"

	"api/internal/apidocs"
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

func NewServer(opts ServerOptions) Server {
	mux := http.NewServeMux()
	humaConfig := huma.DefaultConfig("✣ Ambry API", "1.0.0")
	humaConfig.Info.Description = apidocs.Markdown(apidocs.Overview)
	humaConfig.Tags = []*huma.Tag{
		{Name: "Bible Verses", Description: apidocs.Text(apidocs.Content.Tags.BibleVerses)},
		{Name: "Feast Days", Description: apidocs.Text(apidocs.Content.Tags.FeastDays)},
		{Name: "Patronages", Description: apidocs.Text(apidocs.Content.Tags.Patronages)},
		{Name: "Religious Orders", Description: apidocs.Text(apidocs.Content.Tags.ReligiousOrders)},
		{Name: "Saints", Description: apidocs.Text(apidocs.Content.Tags.Saints)},
		{Name: "Health", Description: apidocs.Text(apidocs.Content.Tags.Health)},
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
		"customCss":          docsCustomCSS,
	}
	humaConfig.Components.SecuritySchemes = map[string]*huma.SecurityScheme{
		"BearerAuth": {
			Type:         "http",
			Scheme:       "bearer",
			BearerFormat: "Developer API key",
			Description:  apidocs.Text(apidocs.Content.Security.BearerAuth),
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
