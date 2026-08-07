package patronage

import (
	"context"
	_ "embed"
	"strings"
	"time"

	"api/internal/database"
	"github.com/jackc/pgx/v5/pgxpool"
)

//go:embed sql/count.sql
var countSQL string

//go:embed sql/list.sql
var listSQL string

type PostgresRepository struct {
	pool         *pgxpool.Pool
	queryTimeout time.Duration
}

func NewPostgresRepository(pool *pgxpool.Pool, queryTimeout time.Duration) PostgresRepository {
	return PostgresRepository{pool: pool, queryTimeout: queryTimeout}
}

func (r PostgresRepository) List(ctx context.Context, filters Filters) (PatronagePage, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	page, perPage := database.NormalizePagination(filters.Page, filters.PerPage)
	query := searchPattern(filters.Query)
	var total int
	if err := r.pool.QueryRow(ctx, countSQL, query).Scan(&total); err != nil {
		return PatronagePage{}, err
	}
	rows, err := r.pool.Query(ctx, listSQL, query, perPage, (page-1)*perPage)
	if err != nil {
		return PatronagePage{}, err
	}
	defer rows.Close()
	items := make([]PatronageItem, 0, perPage)
	for rows.Next() {
		var item PatronageItem
		if err := rows.Scan(&item.ID, &item.Name, &item.Slug, &item.Category, &item.Description, &item.SaintCount); err != nil {
			return PatronagePage{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PatronagePage{}, err
	}
	return PatronagePage{Data: items, Pagination: database.NewPagination(page, perPage, total)}, nil
}

func searchPattern(query string) string {
	query = strings.TrimSpace(query)
	if query == "" {
		return ""
	}
	return "%" + strings.ToLower(query) + "%"
}

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}
