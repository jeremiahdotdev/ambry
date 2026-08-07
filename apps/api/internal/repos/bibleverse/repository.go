package bibleverse

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

func (r PostgresRepository) List(ctx context.Context, filters Filters) (VersePage, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	page, perPage := database.NormalizePagination(filters.Page, filters.PerPage)
	query, book, bookCode, chapter, verse := listParams(filters)

	var total int
	if err := r.pool.QueryRow(ctx, countSQL, query, book, bookCode, chapter, verse).Scan(&total); err != nil {
		return VersePage{}, err
	}

	rows, err := r.pool.Query(ctx, listSQL, query, book, bookCode, chapter, verse, perPage, (page-1)*perPage)
	if err != nil {
		return VersePage{}, err
	}
	defer rows.Close()

	items := make([]Verse, 0, perPage)
	for rows.Next() {
		var item Verse
		var book, bookCode string
		if err := rows.Scan(&item.ID, &book, &bookCode, &item.BookOrder, &item.Chapter, &item.Verse, &item.GreekText, &item.EnglishText, &item.LatinText); err != nil {
			return VersePage{}, err
		}
		item.Book = BookName(book)
		item.BookCode = BookCode(bookCode)
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return VersePage{}, err
	}

	return VersePage{Data: items, Pagination: database.NewPagination(page, perPage, total)}, nil
}

func listParams(filters Filters) (string, string, string, int, int) {
	query := strings.TrimSpace(filters.Query)
	if query != "" {
		query = "%" + strings.ToLower(query) + "%"
	}
	return query,
		strings.ToLower(strings.TrimSpace(filters.Book)),
		strings.ToLower(strings.TrimSpace(filters.BookCode)),
		filters.Chapter,
		filters.Verse
}

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}
