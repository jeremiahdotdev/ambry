package api

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"api/internal/bibleverse"
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
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
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
	for _, path := range []string{"/", "/openapi.json", "/openapi.yaml"} {
		rec := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, path, nil)
		server.Handler.ServeHTTP(rec, req)
		if rec.Code != http.StatusOK {
			t.Fatalf("expected 200 for %s, got %d", path, rec.Code)
		}
	}
}

func TestDocsUseAmbryOverviewAndTheme(t *testing.T) {
	server := testServer(okHealth{})

	docsRec := httptest.NewRecorder()
	docsReq := httptest.NewRequest(http.MethodGet, "/", nil)
	server.Handler.ServeHTTP(docsRec, docsReq)
	if docsRec.Code != http.StatusOK {
		t.Fatalf("expected docs 200, got %d", docsRec.Code)
	}
	docsBody := docsRec.Body.String()
	for _, expected := range []string{"@scalar/api-reference", "customCss", "hideModels", "false", "#42183d", "#fff9ef"} {
		if !strings.Contains(docsBody, expected) {
			t.Fatalf("expected docs body to contain %q", expected)
		}
	}

	openAPIRec := httptest.NewRecorder()
	openAPIReq := httptest.NewRequest(http.MethodGet, "/openapi.json", nil)
	server.Handler.ServeHTTP(openAPIRec, openAPIReq)
	if openAPIRec.Code != http.StatusOK {
		t.Fatalf("expected openapi 200, got %d", openAPIRec.Code)
	}

	var document map[string]any
	if err := json.Unmarshal(openAPIRec.Body.Bytes(), &document); err != nil {
		t.Fatalf("invalid openapi json: %v", err)
	}
	info := document["info"].(map[string]any)
	description := info["description"].(string)
	if !strings.Contains(description, "## Getting Started") || !strings.Contains(description, "Authorization: Bearer") {
		t.Fatalf("openapi overview did not include getting started auth guidance: %q", description)
	}
	if !strings.Contains(description, "60 requests per minute") || !strings.Contains(description, "5,000 requests per day") {
		t.Fatalf("openapi overview did not include account rate limit guidance: %q", description)
	}
	if strings.Contains(openAPIRec.Body.String(), "Allowed book_code/book values") || strings.Contains(openAPIRec.Body.String(), "gen=Genesis") {
		t.Fatal("OpenAPI document should not include prose Bible book mapping")
	}

	components := document["components"].(map[string]any)
	securitySchemes := components["securitySchemes"].(map[string]any)
	if _, ok := securitySchemes["BearerAuth"]; !ok {
		t.Fatal("expected BearerAuth security scheme")
	}
	if !documentContainsEnumValue(document, "gen") || !documentContainsEnumValue(document, "Revelation") {
		t.Fatal("expected OpenAPI document to contain Bible book code and name enum values")
	}
}

func documentContainsEnumValue(value any, expected string) bool {
	switch value := value.(type) {
	case map[string]any:
		if enumValues, ok := value["enum"].([]any); ok {
			for _, enumValue := range enumValues {
				if enumValue == expected {
					return true
				}
			}
		}
		for _, child := range value {
			if documentContainsEnumValue(child, expected) {
				return true
			}
		}
	case []any:
		for _, child := range value {
			if documentContainsEnumValue(child, expected) {
				return true
			}
		}
	}
	return false
}

func TestDocsRouteRemoved(t *testing.T) {
	server := testServer(okHealth{})
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/docs", nil)
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusNotFound {
		t.Fatalf("expected 404, got %d", rec.Code)
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
