package api

import (
	"context"
	"log/slog"
	"net/http"

	"api/internal/apidocs"
	"api/internal/database"
	"github.com/danielgtaylor/huma/v2"
)

type HealthOutput struct {
	Body struct {
		Status   string `json:"status" example:"ok" doc:"Service status."`
		Database string `json:"database" example:"connected" doc:"Database connectivity."`
	}
}

func RegisterHealth(api huma.API, checker database.HealthChecker, logger *slog.Logger) {
	huma.Register(api, huma.Operation{
		OperationID: "get-health",
		Method:      http.MethodGet,
		Path:        "/health",
		Summary:     "Health check",
		Description: apidocs.Text(apidocs.Content.Operations.GetHealth),
		Tags:        []string{"Health"},
	}, func(ctx context.Context, input *struct{}) (*HealthOutput, error) {
		out := &HealthOutput{}
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
