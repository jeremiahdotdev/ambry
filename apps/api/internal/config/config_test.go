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

func TestLoadRedisRESTCredentials(t *testing.T) {
	urlNames := []string{"UPSTASH_REDIS_REST_URL", "KV_REST_API_URL", "REDIS_KV_REST_API_URL"}
	tokenNames := []string{"UPSTASH_REDIS_REST_TOKEN", "KV_REST_API_TOKEN", "REDIS_KV_REST_API_TOKEN"}
	for i, name := range urlNames {
		t.Run(name, func(t *testing.T) {
			t.Setenv("DATABASE_URL", "postgres://example")
			for _, key := range append(append([]string{}, urlNames...), tokenNames...) {
				t.Setenv(key, "")
			}
			t.Setenv(name, " https://example.upstash.io/ ")
			t.Setenv(tokenNames[i], " test-token ")
			cfg, err := Load()
			if err != nil {
				t.Fatalf("load config: %v", err)
			}
			if cfg.UpstashRedisRESTURL != "https://example.upstash.io/" || cfg.UpstashRedisRESTToken != "test-token" {
				t.Fatal("expected trimmed Redis REST credentials from the configured aliases")
			}
		})
	}
}

func TestLoadRedisRESTCredentialPrecedence(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://example")
	t.Setenv("UPSTASH_REDIS_REST_URL", "https://primary.upstash.io")
	t.Setenv("UPSTASH_REDIS_REST_TOKEN", "primary-token")
	t.Setenv("KV_REST_API_URL", "https://kv.upstash.io")
	t.Setenv("KV_REST_API_TOKEN", "kv-token")
	t.Setenv("REDIS_KV_REST_API_URL", "https://redis-kv.upstash.io")
	t.Setenv("REDIS_KV_REST_API_TOKEN", "redis-kv-token")
	for _, expected := range []struct{ url, token string }{
		{"https://primary.upstash.io", "primary-token"},
		{"https://kv.upstash.io", "kv-token"},
	} {
		cfg, err := Load()
		if err != nil {
			t.Fatalf("load config: %v", err)
		}
		if cfg.UpstashRedisRESTURL != expected.url || cfg.UpstashRedisRESTToken != expected.token {
			t.Fatal("Redis REST credential precedence changed")
		}
		t.Setenv("UPSTASH_REDIS_REST_URL", " ")
		t.Setenv("UPSTASH_REDIS_REST_TOKEN", " ")
	}
}
