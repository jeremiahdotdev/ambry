package api

import (
	"log/slog"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
	"github.com/danielgtaylor/huma/v2/adapters/humago"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/auth"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/config"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/database"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/feastday"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/patronage"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/religiousorder"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/saint"
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
	Authenticator   auth.Authenticator
}

func NewServer(opts ServerOptions) Server {
	mux := http.NewServeMux()
	humaConfig := huma.DefaultConfig("Ambry Saints API", "1.0.0")
	humaConfig.Info.Description = "Read-only API for the existing Ambry Catholic saints PostgreSQL database."
	humaConfig.Servers = []*huma.Server{{URL: "/"}}
	api := humago.New(mux, humaConfig)

	RegisterRoutes(api, opts)

	return Server{
		Handler: middlewareChain(mux, opts.Config, opts.Logger, opts.Authenticator),
		API:     api,
	}
}
