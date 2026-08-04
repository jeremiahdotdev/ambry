package patronage

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

func (r PostgresRepository) List(ctx context.Context, filters Filters) (PatronagePage, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	page, perPage := database.NormalizePagination(filters.Page, filters.PerPage)
	where := "where 1=1"
	args := []any{}
	if q := strings.TrimSpace(filters.Query); q != "" {
		args = append(args, "%"+strings.ToLower(q)+"%")
		where += fmt.Sprintf(" and (lower(p.name) like $%d or lower(coalesce(p.description, '')) like $%d)", len(args), len(args))
	}
	var total int
	if err := r.pool.QueryRow(ctx, "select count(*) from patronages p "+where, args...).Scan(&total); err != nil {
		return PatronagePage{}, err
	}
	args = append(args, perPage, (page-1)*perPage)
	sql := `
select p.id::text, p.name, p.slug, p.category, p.description, count(ps.saint_id)::int
from patronages p
left join patronage_saint ps on ps.patronage_id = p.id
` + where + `
group by p.id
order by p.name asc
limit $` + fmt.Sprint(len(args)-1) + ` offset $` + fmt.Sprint(len(args))
	rows, err := r.pool.Query(ctx, sql, args...)
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

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}
