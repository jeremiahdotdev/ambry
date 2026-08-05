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
	ErrRateLimited  = errors.New("api key rate limited")
)

const maxRequestsPerSecond = 10

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
	select id
	from developer_api_keys
	where token_hash = $1
		and revoked_at is null
		and (expires_at is null or expires_at > now())
	limit 1
),
updated_key as (
	update developer_api_keys
	set
		last_used_at = now(),
		updated_at = now(),
		request_window_started_at = date_trunc('second', now()),
		request_window_count = case
			when request_window_started_at = date_trunc('second', now()) then request_window_count + 1
			else 1
		end
	where id = (select id from matching_key)
		and (
			request_window_started_at is null
			or request_window_started_at <> date_trunc('second', now())
			or request_window_count < $2
		)
	returning id
)
select case
	when exists(select 1 from updated_key) then 'ok'
	when exists(select 1 from matching_key) then 'rate_limited'
	else 'invalid'
end
`, tokenHash, maxRequestsPerSecond).Scan(&status)
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
