package config

import (
	"errors"
	"fmt"
	"log/slog"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	DatabaseURL             string
	Port                    string
	AppEnv                  string
	LogLevel                slog.Level
	AllowedOrigins          []string
	DatabaseMaxConnections  int32
	DatabaseMinConnections  int32
	DatabaseMaxConnLifetime time.Duration
	DatabaseMaxConnIdleTime time.Duration
	DatabaseConnectTimeout  time.Duration
	QueryTimeout            time.Duration
	RequestTimeout          time.Duration
	UpstashRedisRESTURL     string
	UpstashRedisRESTToken   string
}

func Load() (Config, error) {
	cfg := Config{
		DatabaseURL:             strings.TrimSpace(os.Getenv("DATABASE_URL")),
		Port:                    env("PORT", "8080"),
		AppEnv:                  env("APP_ENV", "development"),
		LogLevel:                parseLogLevel(env("LOG_LEVEL", "info")),
		AllowedOrigins:          splitCSV(env("ALLOWED_ORIGINS", "*")),
		DatabaseMaxConnections:  int32(envInt("DATABASE_MAX_CONNECTIONS", 3)),
		DatabaseMinConnections:  int32(envInt("DATABASE_MIN_CONNECTIONS", 0)),
		DatabaseMaxConnLifetime: envDuration("DATABASE_MAX_CONN_LIFETIME", 30*time.Minute),
		DatabaseMaxConnIdleTime: envDuration("DATABASE_MAX_CONN_IDLE_TIME", 5*time.Minute),
		DatabaseConnectTimeout:  envDuration("DATABASE_CONNECT_TIMEOUT", 5*time.Second),
		QueryTimeout:            envDuration("QUERY_TIMEOUT", 5*time.Second),
		RequestTimeout:          envDuration("REQUEST_TIMEOUT", 10*time.Second),
		UpstashRedisRESTURL:     firstEnv("UPSTASH_REDIS_REST_URL", "KV_REST_API_URL"),
		UpstashRedisRESTToken:   firstEnv("UPSTASH_REDIS_REST_TOKEN", "KV_REST_API_TOKEN"),
	}
	if cfg.DatabaseURL == "" {
		return Config{}, errors.New("DATABASE_URL is required")
	}
	if cfg.DatabaseMinConnections > cfg.DatabaseMaxConnections {
		return Config{}, fmt.Errorf("DATABASE_MIN_CONNECTIONS cannot exceed DATABASE_MAX_CONNECTIONS")
	}
	return cfg, nil
}

func BootstrapLogger() *slog.Logger {
	return slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
}

func env(key, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		return value
	}
	return fallback
}

func firstEnv(keys ...string) string {
	for _, key := range keys {
		if value := strings.TrimSpace(os.Getenv(key)); value != "" {
			return value
		}
	}
	return ""
}

func envInt(key string, fallback int) int {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := strconv.Atoi(value)
	if err != nil || parsed < 0 {
		return fallback
	}
	return parsed
}

func envDuration(key string, fallback time.Duration) time.Duration {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := time.ParseDuration(value)
	if err != nil {
		return fallback
	}
	return parsed
}

func splitCSV(value string) []string {
	parts := strings.Split(value, ",")
	out := make([]string, 0, len(parts))
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part != "" {
			out = append(out, part)
		}
	}
	return out
}

func parseLogLevel(value string) slog.Level {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "debug":
		return slog.LevelDebug
	case "warn", "warning":
		return slog.LevelWarn
	case "error":
		return slog.LevelError
	default:
		return slog.LevelInfo
	}
}
