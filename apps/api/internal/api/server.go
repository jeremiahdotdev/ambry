package api

import (
	"log/slog"
	"net/http"

	"api/internal/auth"
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
