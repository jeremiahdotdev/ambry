package api

import (
	"github.com/danielgtaylor/huma/v2"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/feastday"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/patronage"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/religiousorder"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/saint"
)

func RegisterRoutes(api huma.API, opts ServerOptions) {
	RegisterHealth(api, opts.Health, opts.Logger)
	saint.RegisterRoutes(api, opts.Saints)
	patronage.RegisterRoutes(api, opts.Patronages)
	religiousorder.RegisterRoutes(api, opts.ReligiousOrders)
	feastday.RegisterRoutes(api, opts.FeastDays)
}
