package health

import (
	"context"
	"log/slog"
	"net/http"

	"api/internal/database"
	"github.com/danielgtaylor/huma/v2"
)

type Output struct {
	Body struct {
		Status   string `json:"status" example:"ok" doc:"Service status."`
		Database string `json:"database" example:"connected" doc:"Database connectivity."`
	}
}

func RegisterRoutes(api huma.API, checker database.HealthChecker, logger *slog.Logger) {
	huma.Register(api, huma.Operation{
		OperationID: "get-health",
		Method:      http.MethodGet,
		Path:        "/health",
		Summary:     "Health check",
		Description: "Returns service status and PostgreSQL connectivity.",
		Tags:        []string{"Health"},
	}, func(ctx context.Context, input *struct{}) (*Output, error) {
		out := &Output{}
		out.Body.Status = "ok"
		if err := checker.Ping(ctx); err != nil {
			logger.Error("health database check failed", "error", err)
			out.Body.Status = "degraded"
			out.Body.Database = "unavailable"
			return out, huma.Error503ServiceUnavailable("Database connectivity is unavailable.")
		}
		out.Body.Database = "connected"
		return out, nil
	})
}
