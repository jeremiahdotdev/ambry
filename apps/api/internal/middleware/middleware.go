package middleware

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"errors"
	"log/slog"
	"net/http"
	"slices"
	"strings"
	"time"

	"api/internal/auth"
	"api/internal/config"
)

type contextKey string

const requestIDKey contextKey = "request_id"

func Chain(next http.Handler, cfg config.Config, logger *slog.Logger, authenticator auth.Authenticator) http.Handler {
	return recoverMiddleware(logger)(
		securityHeaders(
			corsMiddleware(cfg.AllowedOrigins)(
				requestTimeout(cfg.RequestTimeout)(
					requestLogger(logger)(
						requestID(
							knownRouteGuard(
								apiKeyAuth(authenticator, logger)(next),
							),
						),
					),
				),
			),
		),
	)
}

func requestID(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		id := r.Header.Get("X-Request-ID")
		if id == "" {
			id = r.Header.Get("X-Vercel-ID")
		}
		if id == "" {
			id = newRequestID()
		}
		w.Header().Set("X-Request-ID", id)
		ctx := context.WithValue(r.Context(), requestIDKey, id)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

func requestLogger(logger *slog.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			start := time.Now()
			next.ServeHTTP(w, r)
			logger.Info("request completed", "method", r.Method, "path", r.URL.Path, "duration_ms", time.Since(start).Milliseconds(), "request_id", r.Context().Value(requestIDKey))
		})
	}
}

func recoverMiddleware(logger *slog.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				if recovered := recover(); recovered != nil {
					logger.Error("panic recovered", "panic", recovered, "request_id", r.Context().Value(requestIDKey))
					http.Error(w, "internal server error", http.StatusInternalServerError)
				}
			}()
			next.ServeHTTP(w, r)
		})
	}
}

func corsMiddleware(origins []string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			origin := r.Header.Get("Origin")
			if isAllowedOrigin(origins, origin) {
				w.Header().Set("Access-Control-Allow-Origin", origin)
				w.Header().Set("Vary", "Origin")
			} else if slices.Contains(origins, "*") {
				w.Header().Set("Access-Control-Allow-Origin", "*")
			}
			w.Header().Set("Access-Control-Allow-Methods", "GET, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Authorization, Content-Type, X-API-Key, X-Request-ID")
			if r.Method == http.MethodOptions {
				w.WriteHeader(http.StatusNoContent)
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}

func requestTimeout(timeout time.Duration) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.TimeoutHandler(next, timeout, "request timed out")
	}
}

func knownRouteGuard(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		if path == "/" ||
			path == "/health" ||
			path == "/favicon.png" ||
			path == "/openapi.json" ||
			path == "/openapi.yaml" ||
			strings.HasPrefix(path, "/schemas/") ||
			strings.HasPrefix(path, "/api/v1/") {
			next.ServeHTTP(w, r)
			return
		}

		http.NotFound(w, r)
	})
}

func apiKeyAuth(authenticator auth.Authenticator, logger *slog.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.Method == http.MethodOptions || !strings.HasPrefix(r.URL.Path, "/api/v1/") {
				next.ServeHTTP(w, r)
				return
			}

			token := tokenFromRequest(r)
			if token == "" {
				writeAuthError(w, http.StatusUnauthorized, "api_key_required", "API key is required.")
				return
			}
			if authenticator == nil {
				logger.Error("api authenticator is not configured", "request_id", r.Context().Value(requestIDKey))
				writeAuthError(w, http.StatusServiceUnavailable, "api_key_verification_unavailable", "API key verification is temporarily unavailable.")
				return
			}
			if err := authenticator.Authenticate(r.Context(), token); err != nil {
				if errors.Is(err, auth.ErrInvalidToken) {
					writeAuthError(w, http.StatusUnauthorized, "api_key_invalid", "API key is invalid or inactive.")
					return
				}
				if errors.Is(err, auth.ErrRateLimited) {
					writeRateLimitError(w)
					return
				}
				logger.Error("api key verification failed", "error", err, "request_id", r.Context().Value(requestIDKey))
				writeAuthError(w, http.StatusServiceUnavailable, "api_key_verification_unavailable", "API key verification is temporarily unavailable.")
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

func tokenFromRequest(r *http.Request) string {
	authHeader := strings.TrimSpace(r.Header.Get("Authorization"))
	if len(authHeader) >= 7 && strings.EqualFold(authHeader[:7], "bearer ") {
		return strings.TrimSpace(authHeader[7:])
	}
	return strings.TrimSpace(r.Header.Get("X-API-Key"))
}

func writeAuthError(w http.ResponseWriter, status int, code, message string) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_, _ = w.Write([]byte(`{"error":{"code":"` + code + `","message":"` + message + `","details":null}}`))
}

func writeRateLimitError(w http.ResponseWriter) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Retry-After", "60")
	w.WriteHeader(http.StatusTooManyRequests)
	_, _ = w.Write([]byte(`{"error":{"code":"rate_limit_exceeded","message":"Too many requests for this user account.","details":null}}`))
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("Referrer-Policy", "no-referrer")
		next.ServeHTTP(w, r)
	})
}

func isAllowedOrigin(origins []string, origin string) bool {
	if origin == "" {
		return false
	}
	for _, allowed := range origins {
		if strings.EqualFold(allowed, origin) {
			return true
		}
	}
	return false
}

func newRequestID() string {
	var bytes [16]byte
	if _, err := rand.Read(bytes[:]); err != nil {
		return time.Now().UTC().Format("20060102150405.000000000")
	}
	return hex.EncodeToString(bytes[:])
}
