package api

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"api/internal/config"
	"api/internal/database"
	"api/internal/feastday"
	"api/internal/patronage"
	"api/internal/religiousorder"
	"api/internal/saint"
)

type failingHealth struct{}

func (failingHealth) Ping(context.Context) error { return errors.New("db down") }

func testServer(health database.HealthChecker) Server {
	return NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"https://example.com"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          health,
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
	})
}

func TestHealthSuccess(t *testing.T) {
	server := testServer(okHealth{})
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}
}

func TestHealthDatabaseFailure(t *testing.T) {
	server := testServer(failingHealth{})
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusServiceUnavailable {
		t.Fatalf("expected 503, got %d", rec.Code)
	}
}

func TestOpenAPIAndDocsRoutes(t *testing.T) {
	server := testServer(okHealth{})
	for _, path := range []string{"/docs", "/openapi.json", "/openapi.yaml"} {
		rec := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, path, nil)
		server.Handler.ServeHTTP(rec, req)
		if rec.Code != http.StatusOK {
			t.Fatalf("expected 200 for %s, got %d", path, rec.Code)
		}
	}
}

func TestCORSPreflight(t *testing.T) {
	server := testServer(okHealth{})
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodOptions, "/api/v1/saints", nil)
	req.Header.Set("Origin", "https://example.com")
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d", rec.Code)
	}
	if got := rec.Header().Get("Access-Control-Allow-Origin"); got != "https://example.com" {
		t.Fatalf("unexpected allow-origin header: %q", got)
	}
}
