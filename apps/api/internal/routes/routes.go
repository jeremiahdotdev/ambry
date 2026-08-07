package routes

import (
	"log/slog"

	"api/internal/database"
	"api/internal/repos/bibleverse"
	"api/internal/repos/feastday"
	"api/internal/repos/health"
	"api/internal/repos/patronage"
	"api/internal/repos/religiousorder"
	"api/internal/repos/saint"
	"github.com/danielgtaylor/huma/v2"
)

type Options struct {
	Logger          *slog.Logger
	Health          database.HealthChecker
	Saints          saint.Service
	Patronages      patronage.Service
	ReligiousOrders religiousorder.Service
	FeastDays       feastday.Service
	BibleVerses     bibleverse.Service
}

func RegisterRoutes(api huma.API, opts Options) {
	health.RegisterRoutes(api, opts.Health, opts.Logger)
	saint.RegisterRoutes(api, opts.Saints)
	patronage.RegisterRoutes(api, opts.Patronages)
	religiousorder.RegisterRoutes(api, opts.ReligiousOrders)
	feastday.RegisterRoutes(api, opts.FeastDays)
	bibleverse.RegisterRoutes(api, opts.BibleVerses)
}
