package database

import (
	"context"
	"time"

	"api/internal/config"
	"github.com/jackc/pgx/v5/pgxpool"
)

func NewPostgresPool(ctx context.Context, cfg config.Config) (*pgxpool.Pool, error) {
	poolConfig, err := pgxpool.ParseConfig(cfg.DatabaseURL)
	if err != nil {
		return nil, err
	}
	poolConfig.MaxConns = cfg.DatabaseMaxConnections
	poolConfig.MinConns = cfg.DatabaseMinConnections
	poolConfig.MaxConnLifetime = cfg.DatabaseMaxConnLifetime
	poolConfig.MaxConnIdleTime = cfg.DatabaseMaxConnIdleTime
	poolConfig.HealthCheckPeriod = 30 * time.Second
	poolConfig.ConnConfig.ConnectTimeout = cfg.DatabaseConnectTimeout

	pool, err := pgxpool.NewWithConfig(ctx, poolConfig)
	if err != nil {
		return nil, err
	}
	return pool, nil
}

type HealthChecker interface {
	Ping(context.Context) error
}

type poolHealthChecker struct {
	pool    *pgxpool.Pool
	timeout time.Duration
}

func NewHealthChecker(pool *pgxpool.Pool, timeout time.Duration) HealthChecker {
	return poolHealthChecker{pool: pool, timeout: timeout}
}

func (h poolHealthChecker) Ping(ctx context.Context) error {
	timeout := h.timeout
	if timeout <= 0 || timeout > 2*time.Second {
		timeout = 2 * time.Second
	}
	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()
	return h.pool.Ping(ctx)
}
