package api

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"

	"api/internal/config"
)

func TestRuntimeLazyInitializationReusesHandler(t *testing.T) {
	calls := 0
	rt := newRuntime()
	rt.build = func(context.Context, config.Config) (http.Handler, error) {
		calls++
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusNoContent)
		}), nil
	}
	t.Setenv("DATABASE_URL", "postgres://example")

	for range 2 {
		rec := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/health", nil)
		rt.ServeHTTP(rec, req)
		if rec.Code != http.StatusNoContent {
			t.Fatalf("expected 204, got %d", rec.Code)
		}
	}
	if calls != 1 {
		t.Fatalf("expected one initialization, got %d", calls)
	}
}

func TestRuntimeInitializationFailureReturnsSafe503(t *testing.T) {
	rt := newRuntime()
	rt.build = func(context.Context, config.Config) (http.Handler, error) {
		return nil, errors.New("database password leaked in this fake error")
	}
	t.Setenv("DATABASE_URL", "postgres://example")

	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	rt.ServeHTTP(rec, req)
	if rec.Code != http.StatusServiceUnavailable {
		t.Fatalf("expected 503, got %d", rec.Code)
	}
	if body := rec.Body.String(); body == "" || body == "database password leaked in this fake error" {
		t.Fatalf("unsafe response body: %q", body)
	}
}

func TestRuntimeRestoresOriginalVercelRoute(t *testing.T) {
	rt := newRuntime()
	rt.build = func(context.Context, config.Config) (http.Handler, error) {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.URL.Path != "/health" {
				t.Fatalf("expected restored path /health, got %q", r.URL.Path)
			}
			if got := r.URL.Query().Get("route"); got != "" {
				t.Fatalf("expected route query param removed, got %q", got)
			}
			w.WriteHeader(http.StatusNoContent)
		}), nil
	}
	t.Setenv("DATABASE_URL", "postgres://example")

	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/api/index?route=/health", nil)
	rt.ServeHTTP(rec, req)
	if rec.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d", rec.Code)
	}
}

func TestRuntimeRestoresRouteAndPreservesRealQueryParams(t *testing.T) {
	rt := newRuntime()
	rt.build = func(context.Context, config.Config) (http.Handler, error) {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.URL.Path != "/api/v1/saints" {
				t.Fatalf("expected restored path /api/v1/saints, got %q", r.URL.Path)
			}
			if got := r.URL.Query().Get("q"); got != "patrick" {
				t.Fatalf("expected q query param preserved, got %q", got)
			}
			if got := r.URL.Query().Get("route"); got != "" {
				t.Fatalf("expected route query param removed, got %q", got)
			}
			w.WriteHeader(http.StatusNoContent)
		}), nil
	}
	t.Setenv("DATABASE_URL", "postgres://example")

	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/api/index?route=/api/v1/saints&q=patrick", nil)
	rt.ServeHTTP(rec, req)
	if rec.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d", rec.Code)
	}
}

func TestRestoreOriginalRouteLeavesLocalRequestUntouched(t *testing.T) {
	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints?q=patrick", nil)

	restoreOriginalRoute(req)

	if req.URL.Path != "/api/v1/saints" {
		t.Fatalf("expected local path untouched, got %q", req.URL.Path)
	}
	if got := req.URL.Query().Get("q"); got != "patrick" {
		t.Fatalf("expected q query param preserved, got %q", got)
	}
}
