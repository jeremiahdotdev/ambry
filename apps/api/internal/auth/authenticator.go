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

var ErrInvalidToken = errors.New("invalid api key")

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

	var id int64
	err := a.pool.QueryRow(ctx, `
with active_key as (
	select id
	from developer_api_keys
	where token_hash = $1
		and revoked_at is null
		and (expires_at is null or expires_at > now())
	limit 1
)
update developer_api_keys
set last_used_at = now(), updated_at = now()
where id = (select id from active_key)
returning id
`, tokenHash).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return ErrInvalidToken
	}
	return err
}

func HashToken(token string) string {
	sum := sha256.Sum256([]byte(strings.TrimSpace(token)))
	return hex.EncodeToString(sum[:])
}
