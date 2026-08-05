package api

import (
	"context"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"api/internal/auth"
	"api/internal/bibleverse"
	"api/internal/config"
	"api/internal/database"
	"api/internal/feastday"
	"api/internal/patronage"
	"api/internal/religiousorder"
	"api/internal/saint"
)

type okHealth struct{}

func (okHealth) Ping(context.Context) error { return nil }

type acceptingAuthenticator struct{}

func (acceptingAuthenticator) Authenticate(context.Context, string) error { return nil }

type rejectingAuthenticator struct{}

func (rejectingAuthenticator) Authenticate(context.Context, string) error {
	return auth.ErrInvalidToken
}

type rateLimitedAuthenticator struct{}

func (rateLimitedAuthenticator) Authenticate(context.Context, string) error {
	return auth.ErrRateLimited
}

type saintRepoFake struct{}

func (saintRepoFake) Search(context.Context, saint.SearchFilters) (saint.SearchPage, error) {
	return saint.SearchPage{
		Data: []saint.SearchResult{{
			ID:              "00000000-0000-0000-0000-000000000001",
			PrimaryName:     "St. Test",
			Slug:            "st-test",
			CanonicalStatus: "saint",
			FeastDays:       []saint.FeastDay{},
			Patronages:      []string{},
			ReligiousOrders: []string{},
		}},
		Pagination: database.NewPagination(1, 20, 1),
	}, nil
}

func (saintRepoFake) GetBySlug(context.Context, string) (saint.Detail, error) {
	return saint.Detail{}, saint.ErrNotFound
}

type emptyPatronageRepo struct{}

func (emptyPatronageRepo) List(context.Context, patronage.Filters) (patronage.PatronagePage, error) {
	return patronage.PatronagePage{Data: []patronage.PatronageItem{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type emptyOrderRepo struct{}

func (emptyOrderRepo) List(context.Context, religiousorder.Filters) (religiousorder.ReligiousOrderPage, error) {
	return religiousorder.ReligiousOrderPage{Data: []religiousorder.ReligiousOrderItem{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type emptyFeastRepo struct{}

func (emptyFeastRepo) List(context.Context, feastday.Filters) (feastday.FeastDayPage, error) {
	return feastday.FeastDayPage{Data: []saint.SearchResult{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type bibleVerseRepoFake struct{}

func (bibleVerseRepoFake) List(context.Context, bibleverse.Filters) (bibleverse.VersePage, error) {
	return bibleverse.VersePage{
		Data: []bibleverse.Verse{{
			ID:        "newadvent-bible-gen-001-001",
			Book:      "Genesis",
			BookCode:  "gen",
			BookOrder: 1,
			Chapter:   1,
			Verse:     1,
		}},
		Pagination: database.NewPagination(1, 20, 1),
	}, nil
}

func TestListSaintsHandlerSuccess(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   acceptingAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints?q=test", nil)
	req.Header.Set("Authorization", "Bearer saints_test_valid")
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d: %s", rec.Code, rec.Body.String())
	}
}

func TestListBibleVersesHandlerSuccess(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   acceptingAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/bible-verses?book_code=gen&chapter=1", nil)
	req.Header.Set("Authorization", "Bearer saints_test_valid")
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d: %s", rec.Code, rec.Body.String())
	}
}

func TestGetSaintNotFound(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   acceptingAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints/missing", nil)
	req.Header.Set("Authorization", "Bearer saints_test_valid")
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusNotFound {
		t.Fatalf("expected 404, got %d", rec.Code)
	}
}

func TestAPIKeyRequired(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   acceptingAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints?q=test", nil)
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusUnauthorized {
		t.Fatalf("expected 401, got %d: %s", rec.Code, rec.Body.String())
	}
}

func TestAPIKeyInvalid(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   rejectingAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints?q=test", nil)
	req.Header.Set("X-API-Key", "saints_test_invalid")
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusUnauthorized {
		t.Fatalf("expected 401, got %d: %s", rec.Code, rec.Body.String())
	}
}

func TestAPIKeyRateLimit(t *testing.T) {
	server := NewServer(ServerOptions{
		Config: config.Config{
			Port:           "8080",
			AllowedOrigins: []string{"*"},
			RequestTimeout: 5 * time.Second,
		},
		Logger:          slog.Default(),
		Health:          okHealth{},
		Saints:          saint.NewService(saintRepoFake{}),
		Patronages:      patronage.NewService(emptyPatronageRepo{}),
		ReligiousOrders: religiousorder.NewService(emptyOrderRepo{}),
		FeastDays:       feastday.NewService(emptyFeastRepo{}),
		BibleVerses:     bibleverse.NewService(bibleVerseRepoFake{}),
		Authenticator:   rateLimitedAuthenticator{},
	})

	req := httptest.NewRequest(http.MethodGet, "/api/v1/saints?q=test", nil)
	req.Header.Set("Authorization", "Bearer saints_test_rate_limited")
	rec := httptest.NewRecorder()
	server.Handler.ServeHTTP(rec, req)
	if rec.Code != http.StatusTooManyRequests {
		t.Fatalf("expected 429, got %d: %s", rec.Code, rec.Body.String())
	}
	if got := rec.Header().Get("Retry-After"); got != "1" {
		t.Fatalf("expected Retry-After 1, got %q", got)
	}
}
