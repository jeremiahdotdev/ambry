package config

import "testing"

func TestLoadRequiresDatabaseURL(t *testing.T) {
	t.Setenv("DATABASE_URL", "")
	_, err := Load()
	if err == nil {
		t.Fatal("expected DATABASE_URL validation error")
	}
}

func TestLoadDefaults(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://example")
	cfg, err := Load()
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if cfg.Port != "8080" {
		t.Fatalf("expected default port 8080, got %s", cfg.Port)
	}
	if cfg.DatabaseMaxConnections != 3 {
		t.Fatalf("expected default max connections 3, got %d", cfg.DatabaseMaxConnections)
	}
	if cfg.DatabaseMinConnections != 0 {
		t.Fatalf("expected default min connections 0, got %d", cfg.DatabaseMinConnections)
	}
}
