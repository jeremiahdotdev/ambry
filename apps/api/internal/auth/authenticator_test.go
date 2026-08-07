package auth

import (
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func TestUpstashRateLimiterAllowsAccountWhenRedisReturnsOne(t *testing.T) {
	var command []any
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if got := r.Header.Get("Authorization"); got != "Bearer test-token" {
			t.Fatalf("expected bearer token, got %q", got)
		}
		if err := json.NewDecoder(r.Body).Decode(&command); err != nil {
			t.Fatalf("decode command: %v", err)
		}
		_, _ = w.Write([]byte(`{"result":1}`))
	}))
	defer server.Close()

	limiter := NewUpstashRateLimiter(server.URL, "test-token")
	limiter.now = func() time.Time {
		return time.Date(2026, 8, 5, 12, 0, 30, 0, time.UTC)
	}

	accepted, err := limiter.Allow(t.Context(), 42)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if !accepted {
		t.Fatal("expected request to be accepted")
	}

	if command[0] != "EVAL" {
		t.Fatalf("expected EVAL command, got %#v", command[0])
	}
	if command[3] != "ambry:rate:user:42:minute:202608051200" {
		t.Fatalf("unexpected minute key: %#v", command[3])
	}
	if command[4] != "ambry:rate:user:42:day:20260805" {
		t.Fatalf("unexpected day key: %#v", command[4])
	}
	if command[8] != "86460" {
		t.Fatalf("unexpected day ttl: %#v", command[8])
	}
}

func TestUpstashRateLimiterRejectsAccountWhenRedisReturnsZero(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write([]byte(`{"result":0}`))
	}))
	defer server.Close()

	accepted, err := NewUpstashRateLimiter(server.URL, "test-token").Allow(t.Context(), 42)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if accepted {
		t.Fatal("expected request to be rejected")
	}
}

func TestUpstashRateLimiterReturnsUnavailableForTransportErrors(t *testing.T) {
	limiter := NewUpstashRateLimiter("http://127.0.0.1:1", "test-token")
	limiter.httpClient.Timeout = time.Millisecond

	_, err := limiter.Allow(t.Context(), 42)
	if !errors.Is(err, ErrRateLimiterUnavailable) {
		t.Fatalf("expected ErrRateLimiterUnavailable, got %v", err)
	}
}

func TestHashTokenHashesExactBytes(t *testing.T) {
	withSpaces := HashToken(" saints_test_exact ")
	withoutSpaces := HashToken("saints_test_exact")

	if withSpaces == withoutSpaces {
		t.Fatal("expected HashToken to preserve leading and trailing spaces")
	}
}
