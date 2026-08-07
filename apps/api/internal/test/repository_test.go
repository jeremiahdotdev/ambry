//go:build integration

package integration

import (
	"os"
	"strings"
	"testing"
)

func TestIntegrationDatabaseURLIsExplicitAndLocal(t *testing.T) {
	url := os.Getenv("TEST_DATABASE_URL")
	if url == "" {
		t.Skip("TEST_DATABASE_URL is required for integration tests")
	}
	if !strings.Contains(url, "localhost") && !strings.Contains(url, "127.0.0.1") && !strings.Contains(url, "postgres:") {
		t.Fatal("refusing to run integration tests against a non-local database URL")
	}
}
