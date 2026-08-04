package main

import (
	"context"
	"errors"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"api/internal/app"
	"api/internal/config"
)

func main() {
	ctx := context.Background()
	cfg, err := config.Load()
	if err != nil {
		config.BootstrapLogger().Error("configuration error", "error", err)
		os.Exit(1)
	}

	application, err := app.New(ctx, cfg)
	if err != nil {
		config.BootstrapLogger().Error("application initialization failed", "error", err)
		os.Exit(1)
	}
	defer application.Close()

	httpServer := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           application.Handler,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       15 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       60 * time.Second,
	}

	errs := make(chan error, 1)
	go func() {
		application.Logger.Info("api server listening", "addr", httpServer.Addr, "docs", "/")
		errs <- httpServer.ListenAndServe()
	}()

	shutdownCtx, stop := signal.NotifyContext(ctx, os.Interrupt, syscall.SIGTERM)
	defer stop()

	select {
	case <-shutdownCtx.Done():
		application.Logger.Info("shutdown requested")
	case err := <-errs:
		if !errors.Is(err, http.ErrServerClosed) {
			application.Logger.Error("server stopped unexpectedly", "error", err)
			os.Exit(1)
		}
	}

	ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()
	if err := httpServer.Shutdown(ctx); err != nil {
		application.Logger.Error("graceful shutdown failed", "error", err)
		os.Exit(1)
	}
	application.Logger.Info("server stopped")
}
