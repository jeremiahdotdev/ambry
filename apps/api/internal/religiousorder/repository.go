package religiousorder

import (
	"context"
	"fmt"
	"strings"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/database"
)

type PostgresRepository struct {
	pool         *pgxpool.Pool
	queryTimeout time.Duration
}

func NewPostgresRepository(pool *pgxpool.Pool, queryTimeout time.Duration) PostgresRepository {
	return PostgresRepository{pool: pool, queryTimeout: queryTimeout}
}

func (r PostgresRepository) List(ctx context.Context, filters Filters) (ReligiousOrderPage, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	page, perPage := database.NormalizePagination(filters.Page, filters.PerPage)
	where := "where 1=1"
	args := []any{}
	if q := strings.TrimSpace(filters.Query); q != "" {
		args = append(args, "%"+strings.ToLower(q)+"%")
		where += fmt.Sprintf(" and (lower(ro.name) like $%d or lower(coalesce(ro.description, '')) like $%d or lower(coalesce(ro.abbreviation, '')) like $%d)", len(args), len(args), len(args))
	}
	var total int
	if err := r.pool.QueryRow(ctx, "select count(*) from religious_orders ro "+where, args...).Scan(&total); err != nil {
		return ReligiousOrderPage{}, err
	}
	args = append(args, perPage, (page-1)*perPage)
	sql := `
select ro.id::text, ro.name, ro.slug, ro.abbreviation, ro.description, count(ros.saint_id)::int
from religious_orders ro
left join religious_order_saint ros on ros.religious_order_id = ro.id
` + where + `
group by ro.id
order by ro.name asc
limit $` + fmt.Sprint(len(args)-1) + ` offset $` + fmt.Sprint(len(args))
	rows, err := r.pool.Query(ctx, sql, args...)
	if err != nil {
		return ReligiousOrderPage{}, err
	}
	defer rows.Close()
	items := make([]ReligiousOrderItem, 0, perPage)
	for rows.Next() {
		var item ReligiousOrderItem
		if err := rows.Scan(&item.ID, &item.Name, &item.Slug, &item.Abbreviation, &item.Description, &item.SaintCount); err != nil {
			return ReligiousOrderPage{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return ReligiousOrderPage{}, err
	}
	return ReligiousOrderPage{Data: items, Pagination: database.NewPagination(page, perPage, total)}, nil
}

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}
