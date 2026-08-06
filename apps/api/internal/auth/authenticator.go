package auth

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
)

var (
	ErrInvalidToken = errors.New("invalid api key")
	ErrRateLimited  = errors.New("user account rate limited")
)

const maxRequestsPerDay = 5000
const maxRequestsPerMinute = 60

type Authenticator interface {
	Authenticate(ctx context.Context, token string) error
}

type PostgresAuthenticator struct {
	pool         *pgxpool.Pool
	queryTimeout time.Duration
}

func NewPostgresAuthenticator(pool *pgxpool.Pool, queryTimeout time.Duration) PostgresAuthenticator {
	return PostgresAuthenticator{pool: pool, queryTimeout: queryTimeout}
}

func (a PostgresAuthenticator) Authenticate(ctx context.Context, token string) error {
	tokenHash := HashToken(token)
	timeout := a.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	var status string
	err := a.pool.QueryRow(ctx, `
with matching_key as (
	select id, user_id
	from developer_api_keys
	where token_hash = $1
		and revoked_at is null
		and (expires_at is null or expires_at > now())
	limit 1
),
accepted_account as (
	update users
	set
		api_minute_window_started_at = date_trunc('minute', now()),
		api_minute_request_count = case
			when api_minute_window_started_at = date_trunc('minute', now()) then api_minute_request_count + 1
			else 1
		end,
		api_day_window_started_at = date_trunc('day', now()),
		api_day_request_count = case
			when api_day_window_started_at = date_trunc('day', now()) then api_day_request_count + 1
			else 1
		end,
		updated_at = now()
	where id = (select user_id from matching_key)
		and (
			api_minute_window_started_at is null
			or api_minute_window_started_at <> date_trunc('minute', now())
			or api_minute_request_count < $2
		)
		and (
			api_day_window_started_at is null
			or api_day_window_started_at <> date_trunc('day', now())
			or api_day_request_count < $3
		)
	returning id
),
updated_key as (
	update developer_api_keys
	set
		last_used_at = now(),
		updated_at = now()
	where id = (select id from matching_key)
		and exists(select 1 from accepted_account)
	returning id
)
select case
	when exists(select 1 from updated_key) then 'ok'
	when exists(select 1 from matching_key) then 'rate_limited'
	else 'invalid'
end
`, tokenHash, maxRequestsPerMinute, maxRequestsPerDay).Scan(&status)
	if errors.Is(err, pgx.ErrNoRows) || status == "invalid" {
		return ErrInvalidToken
	}
	if status == "rate_limited" {
		return ErrRateLimited
	}
	return err
}

func HashToken(token string) string {
	sum := sha256.Sum256([]byte(strings.TrimSpace(token)))
	return hex.EncodeToString(sum[:])
}
