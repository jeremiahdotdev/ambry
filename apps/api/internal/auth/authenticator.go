package auth

import (
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"

	"api/internal/config"
	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
)

var (
	ErrInvalidToken           = errors.New("invalid api key")
	ErrRateLimited            = errors.New("user account rate limited")
	ErrRateLimiterUnavailable = errors.New("account rate limiter unavailable")
)

const maxRequestsPerDay = 5000
const maxRequestsPerMinute = 60

type Authenticator interface {
	Authenticate(ctx context.Context, token string) error
}

type AccountRateLimiter interface {
	Allow(ctx context.Context, userID int64) (bool, error)
}

type APIKeyAuthenticator struct {
	pool         *pgxpool.Pool
	queryTimeout time.Duration
	rateLimiter  AccountRateLimiter
}

func NewAPIKeyAuthenticator(pool *pgxpool.Pool, queryTimeout time.Duration, rateLimiter AccountRateLimiter) APIKeyAuthenticator {
	return APIKeyAuthenticator{pool: pool, queryTimeout: queryTimeout, rateLimiter: rateLimiter}
}

func (a APIKeyAuthenticator) Authenticate(ctx context.Context, token string) error {
	tokenHash := HashToken(token)
	timeout := a.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	if a.rateLimiter != nil {
		return a.authenticateWithExternalRateLimiter(ctx, tokenHash)
	}

	return ErrRateLimiterUnavailable
}

func (a APIKeyAuthenticator) authenticateWithExternalRateLimiter(ctx context.Context, tokenHash string) error {
	var keyID int64
	var userID int64
	err := a.pool.QueryRow(ctx, `
select id, user_id
from developer_api_keys
where token_hash = $1
	and revoked_at is null
	and (expires_at is null or expires_at > now())
limit 1
`, tokenHash).Scan(&keyID, &userID)
	if errors.Is(err, pgx.ErrNoRows) {
		return ErrInvalidToken
	}
	if err != nil {
		return err
	}

	accepted, err := a.rateLimiter.Allow(ctx, userID)
	if err != nil {
		return err
	}
	if !accepted {
		return ErrRateLimited
	}

	_, err = a.pool.Exec(ctx, `
update developer_api_keys
set
	last_used_at = now(),
	updated_at = now()
where id = $1
`, keyID)
	return err
}

type UpstashRateLimiter struct {
	restURL    string
	token      string
	httpClient *http.Client
	now        func() time.Time
}

func NewUpstashRateLimiterFromConfig(cfg config.Config) *UpstashRateLimiter {
	if cfg.UpstashRedisRESTURL == "" || cfg.UpstashRedisRESTToken == "" {
		return nil
	}
	return NewUpstashRateLimiter(cfg.UpstashRedisRESTURL, cfg.UpstashRedisRESTToken)
}

func NewUpstashRateLimiter(restURL, token string) *UpstashRateLimiter {
	return &UpstashRateLimiter{
		restURL: strings.TrimRight(strings.TrimSpace(restURL), "/"),
		token:   strings.TrimSpace(token),
		httpClient: &http.Client{
			Timeout: 2 * time.Second,
		},
		now: time.Now,
	}
}

func (l *UpstashRateLimiter) Allow(ctx context.Context, userID int64) (bool, error) {
	if l == nil || l.restURL == "" || l.token == "" {
		return false, ErrRateLimiterUnavailable
	}

	now := l.now().UTC()
	minuteKey := fmt.Sprintf("ambry:rate:user:%d:minute:%s", userID, now.Format("200601021504"))
	dayKey := fmt.Sprintf("ambry:rate:user:%d:day:%s", userID, now.Format("20060102"))

	return l.evalLimit(ctx, minuteKey, dayKey)
}

func (l *UpstashRateLimiter) evalLimit(ctx context.Context, minuteKey, dayKey string) (bool, error) {
	command := []any{
		"EVAL",
		upstashRateLimitScript,
		"2",
		minuteKey,
		dayKey,
		maxRequestsPerMinute,
		maxRequestsPerDay,
		"70",
		"90000",
	}
	body, err := json.Marshal(command)
	if err != nil {
		return false, err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, l.restURL, bytes.NewReader(body))
	if err != nil {
		return false, err
	}
	req.Header.Set("Authorization", "Bearer "+l.token)
	req.Header.Set("Content-Type", "application/json")

	resp, err := l.httpClient.Do(req)
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()

	var payload struct {
		Result any    `json:"result"`
		Error  string `json:"error"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		return false, err
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || payload.Error != "" {
		if payload.Error != "" {
			return false, errors.New(payload.Error)
		}
		return false, fmt.Errorf("upstash returned status %d", resp.StatusCode)
	}

	switch result := payload.Result.(type) {
	case float64:
		return result == 1, nil
	case string:
		return result == "1", nil
	case bool:
		return result, nil
	default:
		return false, fmt.Errorf("unexpected upstash result %T", payload.Result)
	}
}

const upstashRateLimitScript = `
local minute_count = tonumber(redis.call("GET", KEYS[1]) or "0")
local day_count = tonumber(redis.call("GET", KEYS[2]) or "0")
if minute_count >= tonumber(ARGV[1]) or day_count >= tonumber(ARGV[2]) then
	return 0
end
minute_count = redis.call("INCR", KEYS[1])
if minute_count == 1 then
	redis.call("EXPIRE", KEYS[1], tonumber(ARGV[3]))
end
day_count = redis.call("INCR", KEYS[2])
if day_count == 1 then
	redis.call("EXPIRE", KEYS[2], tonumber(ARGV[4]))
end
return 1
`

func HashToken(token string) string {
	sum := sha256.Sum256([]byte(strings.TrimSpace(token)))
	return hex.EncodeToString(sum[:])
}
