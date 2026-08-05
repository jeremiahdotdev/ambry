package bibleverse

import (
	"context"
	"fmt"
	"strings"
	"time"

	"api/internal/database"
	"github.com/jackc/pgx/v5/pgxpool"
)

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
	where := "where 1=1"
	args := []any{}
	if q := strings.TrimSpace(filters.Query); q != "" {
		args = append(args, "%"+strings.ToLower(q)+"%")
		where += fmt.Sprintf(` and (
lower(bv.book) like $%d
or lower(coalesce(bv.greek_text, '')) like $%d
or lower(coalesce(bv.english_text, '')) like $%d
or lower(coalesce(bv.latin_text, '')) like $%d
)`, len(args), len(args), len(args), len(args))
	}
	if book := strings.TrimSpace(filters.Book); book != "" {
		args = append(args, strings.ToLower(book))
		where += fmt.Sprintf(" and lower(bv.book) = $%d", len(args))
	}
	if bookCode := strings.TrimSpace(filters.BookCode); bookCode != "" {
		args = append(args, strings.ToLower(bookCode))
		where += fmt.Sprintf(" and lower(bv.book_code) = $%d", len(args))
	}
	if filters.Chapter > 0 {
		args = append(args, filters.Chapter)
		where += fmt.Sprintf(" and bv.chapter = $%d", len(args))
	}
	if filters.Verse > 0 {
		args = append(args, filters.Verse)
		where += fmt.Sprintf(" and bv.verse = $%d", len(args))
	}

	var total int
	if err := r.pool.QueryRow(ctx, "select count(*) from bible_verses bv "+where, args...).Scan(&total); err != nil {
		return VersePage{}, err
	}

	args = append(args, perPage, (page-1)*perPage)
	sql := `
select bv.id, bv.book, bv.book_code, bv.book_order, bv.chapter, bv.verse, bv.greek_text, bv.english_text, bv.latin_text
from bible_verses bv
` + where + `
order by bv.book_order asc, bv.chapter asc, bv.verse asc
limit $` + fmt.Sprint(len(args)-1) + ` offset $` + fmt.Sprint(len(args))
	rows, err := r.pool.Query(ctx, sql, args...)
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

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}
