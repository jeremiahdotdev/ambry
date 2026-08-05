package app

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"time"

	"api/internal/api"
	"api/internal/auth"
	"api/internal/bibleverse"
	"api/internal/config"
	"api/internal/database"
	"api/internal/feastday"
	"api/internal/patronage"
	"api/internal/religiousorder"
	"api/internal/saint"
	"github.com/jackc/pgx/v5/pgxpool"
)

type App struct {
	Handler http.Handler
	Logger  *slog.Logger
	pool    *pgxpool.Pool
}

func New(ctx context.Context, cfg config.Config) (*App, error) {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: cfg.LogLevel}))
	pool, err := database.NewPostgresPool(ctx, cfg)
	if err != nil {
		logger.Error("database pool initialization failed", "error", err)
		return nil, err
	}

	saintRepo := saint.NewPostgresRepository(pool, cfg.QueryTimeout)
	patronageRepo := patronage.NewPostgresRepository(pool, cfg.QueryTimeout)
	orderRepo := religiousorder.NewPostgresRepository(pool, cfg.QueryTimeout)
	feastDayRepo := feastday.NewPostgresRepository(pool, cfg.QueryTimeout)
	bibleVerseRepo := bibleverse.NewPostgresRepository(pool, cfg.QueryTimeout)

	server := api.NewServer(api.ServerOptions{
		Config:          cfg,
		Logger:          logger,
		Health:          database.NewHealthChecker(pool, cfg.QueryTimeout),
		Saints:          saint.NewService(saintRepo),
		Patronages:      patronage.NewService(patronageRepo),
		ReligiousOrders: religiousorder.NewService(orderRepo),
		FeastDays:       feastday.NewService(feastDayRepo),
		BibleVerses:     bibleverse.NewService(bibleVerseRepo),
		Authenticator:   auth.NewPostgresAuthenticator(pool, cfg.QueryTimeout),
	})

	return &App{Handler: server.Handler, Logger: logger, pool: pool}, nil
}

func NewFromEnvironment(ctx context.Context) (*App, error) {
	cfg, err := config.Load()
	if err != nil {
		return nil, err
	}
	initCtx, cancel := context.WithTimeout(ctx, cfg.DatabaseConnectTimeout)
	defer cancel()
	return New(initCtx, cfg)
}

func NewHandler(ctx context.Context, cfg config.Config) (http.Handler, error) {
	application, err := New(ctx, cfg)
	if err != nil {
		return nil, err
	}
	return application.Handler, nil
}

func (a *App) Close() {
	if a != nil && a.pool != nil {
		a.pool.Close()
	}
}

func InitContext(timeout time.Duration) (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.Background(), timeout)
}
