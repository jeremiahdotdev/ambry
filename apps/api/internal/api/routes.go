package api

import (
	"api/internal/feastday"
	"api/internal/patronage"
	"api/internal/religiousorder"
	"api/internal/saint"
	"github.com/danielgtaylor/huma/v2"
)

func RegisterRoutes(api huma.API, opts ServerOptions) {
	RegisterHealth(api, opts.Health, opts.Logger)
	saint.RegisterRoutes(api, opts.Saints)
	patronage.RegisterRoutes(api, opts.Patronages)
	religiousorder.RegisterRoutes(api, opts.ReligiousOrders)
	feastday.RegisterRoutes(api, opts.FeastDays)
}
