package api

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"strings"
	"sync"

	"api/internal/app"
	"api/internal/config"
)

var defaultRuntime = newRuntime()

type runtime struct {
	mu      sync.Mutex
	handler http.Handler
	initErr error
	ready   bool
	build   func(context.Context, config.Config) (http.Handler, error)
	logger  *slog.Logger
}

func newRuntime() *runtime {
	return &runtime{
		build: func(ctx context.Context, cfg config.Config) (http.Handler, error) {
			application, err := app.New(ctx, cfg)
			if err != nil {
				return nil, err
			}
			return application.Handler, nil
		},
		logger: config.BootstrapLogger(),
	}
}

func Handler(w http.ResponseWriter, r *http.Request) {
	defaultRuntime.ServeHTTP(w, r)
}

func (rt *runtime) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	handler, err := rt.get()
	if err != nil {
		rt.logger.Error("vercel handler initialization failed", "error", err)
		writeServiceUnavailable(w)
		return
	}
	restoreOriginalRoute(r)
	handler.ServeHTTP(w, r)
}

func (rt *runtime) get() (http.Handler, error) {
	rt.mu.Lock()
	defer rt.mu.Unlock()
	if rt.ready {
		return rt.handler, rt.initErr
	}
	cfg, err := config.Load()
	if err != nil {
		rt.initErr = err
		rt.ready = true
		return nil, err
	}
	initCtx, cancel := app.InitContext(cfg.DatabaseConnectTimeout)
	defer cancel()
	handler, err := rt.build(initCtx, cfg)
	if err != nil {
		rt.initErr = err
		rt.ready = true
		return nil, err
	}
	rt.handler = handler
	rt.ready = true
	return handler, nil
}

func writeServiceUnavailable(w http.ResponseWriter) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusServiceUnavailable)
	_ = json.NewEncoder(w).Encode(map[string]any{
		"error": map[string]any{
			"code":    "service_unavailable",
			"message": "The API is temporarily unavailable.",
			"details": nil,
		},
	})
}

func restoreOriginalRoute(r *http.Request) {
	query := r.URL.Query()
	route := query.Get("route")
	if route == "" {
		return
	}
	if !strings.HasPrefix(route, "/") {
		route = "/" + route
	}

	r.URL.Path = route
	r.URL.RawPath = ""

	query.Del("route")
	r.URL.RawQuery = query.Encode()
}
