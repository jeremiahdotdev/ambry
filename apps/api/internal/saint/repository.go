package saint

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
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

func (r PostgresRepository) Search(ctx context.Context, filters SearchFilters) (SearchPage, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	page, perPage := database.NormalizePagination(filters.Page, filters.PerPage)
	where, args := buildSaintWhere(filters)
	countSQL := "select count(distinct s.id) from saints s " + saintSearchJoins(filters) + where

	var total int
	if err := r.pool.QueryRow(ctx, countSQL, args...).Scan(&total); err != nil {
		return SearchPage{}, err
	}

	order := saintOrderBy(filters.Sort, filters.Direction)
	args = append(args, perPage, (page-1)*perPage)
	sql := `
select
	s.id::text,
	s.primary_name,
	s.slug,
	s.canonical_status,
	s.birth_year,
	s.death_year,
	coalesce(nullif(s.profile_summary, ''), left(coalesce(s.biography, ''), 250)) as summary,
	coalesce(jsonb_agg(distinct jsonb_build_object('month', fd.month, 'day', fd.day, 'name', fd.calendar)) filter (where fd.id is not null), '[]'::jsonb) as feast_days,
	coalesce(array_remove(array_agg(distinct p.name), null::varchar), '{}') as patronages,
	coalesce(array_remove(array_agg(distinct ro.name), null::varchar), '{}') as religious_orders
from saints s
left join feast_days fd on fd.saint_id = s.id
left join patronage_saint ps on ps.saint_id = s.id
left join patronages p on p.id = ps.patronage_id
left join religious_order_saint ros on ros.saint_id = s.id
left join religious_orders ro on ro.id = ros.religious_order_id
` + where + `
group by s.id
` + order + `
limit $` + fmt.Sprint(len(args)-1) + ` offset $` + fmt.Sprint(len(args))

	rows, err := r.pool.Query(ctx, sql, args...)
	if err != nil {
		return SearchPage{}, err
	}
	defer rows.Close()

	results := make([]SearchResult, 0, perPage)
	for rows.Next() {
		var item SearchResult
		var feastJSON []byte
		if err := rows.Scan(&item.ID, &item.PrimaryName, &item.Slug, &item.CanonicalStatus, &item.BirthYear, &item.DeathYear, &item.Summary, &feastJSON, &item.Patronages, &item.ReligiousOrders); err != nil {
			return SearchPage{}, err
		}
		if err := json.Unmarshal(feastJSON, &item.FeastDays); err != nil {
			return SearchPage{}, err
		}
		results = append(results, item)
	}
	if err := rows.Err(); err != nil {
		return SearchPage{}, err
	}

	return SearchPage{Data: results, Pagination: database.NewPagination(page, perPage, total)}, nil
}

func (r PostgresRepository) GetBySlug(ctx context.Context, slug string) (Detail, error) {
	ctx, cancel := r.withQueryTimeout(ctx)
	defer cancel()

	tx, err := r.pool.Begin(ctx)
	if err != nil {
		return Detail{}, err
	}
	defer tx.Rollback(ctx)

	var detail Detail
	var locationsJSON []byte
	var titlesJSON []byte
	err = tx.QueryRow(ctx, `
select
	id::text,
	primary_name,
	slug,
	canonical_status,
	biography,
	birth_year,
	death_year,
	coalesce(profile_landmarks, '[]'::json)::jsonb,
	coalesce(profile_church_roles, roles, '[]'::json)::jsonb
from saints
where slug = $1
`, slug).Scan(&detail.ID, &detail.PrimaryName, &detail.Slug, &detail.CanonicalStatus, &detail.Biography, &detail.BirthYear, &detail.DeathYear, &locationsJSON, &titlesJSON)
	if errors.Is(err, pgx.ErrNoRows) {
		return Detail{}, ErrNotFound
	}
	if err != nil {
		return Detail{}, err
	}

	detail.Aliases, err = queryStringList(ctx, tx, `select alias from saint_aliases where saint_id = $1 order by alias`, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.Patronages, err = queryStringList(ctx, tx, `select p.name from patronages p join patronage_saint ps on ps.patronage_id = p.id where ps.saint_id = $1 order by p.name`, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.ReligiousOrders, err = queryStringList(ctx, tx, `select ro.name from religious_orders ro join religious_order_saint ros on ros.religious_order_id = ro.id where ros.saint_id = $1 order by ro.name`, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.FeastDays, err = queryFeastDays(ctx, tx, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.Locations = parseLocations(locationsJSON)
	detail.Titles = parseTitles(titlesJSON)

	if err := tx.Commit(ctx); err != nil {
		return Detail{}, err
	}
	return detail, nil
}

func (r PostgresRepository) withQueryTimeout(ctx context.Context) (context.Context, context.CancelFunc) {
	timeout := r.queryTimeout
	if timeout <= 0 {
		timeout = 5 * time.Second
	}
	return context.WithTimeout(ctx, timeout)
}

type querier interface {
	Query(context.Context, string, ...any) (pgx.Rows, error)
}

func queryStringList(ctx context.Context, q querier, sql string, saintID string) ([]string, error) {
	rows, err := q.Query(ctx, sql, saintID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	values := []string{}
	for rows.Next() {
		var value string
		if err := rows.Scan(&value); err != nil {
			return nil, err
		}
		values = append(values, value)
	}
	return values, rows.Err()
}

func queryFeastDays(ctx context.Context, q querier, saintID string) ([]FeastDay, error) {
	rows, err := q.Query(ctx, `select month, day, calendar from feast_days where saint_id = $1 order by month, day, calendar`, saintID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []FeastDay{}
	for rows.Next() {
		var item FeastDay
		if err := rows.Scan(&item.Month, &item.Day, &item.Name); err != nil {
			return nil, err
		}
		out = append(out, item)
	}
	return out, rows.Err()
}

func buildSaintWhere(filters SearchFilters) (string, []any) {
	clauses := []string{"1=1"}
	args := []any{}
	add := func(clause string, value any) {
		args = append(args, value)
		clauses = append(clauses, fmt.Sprintf(clause, len(args)))
	}
	if q := strings.TrimSpace(filters.Query); q != "" {
		like := "%" + strings.ToLower(q) + "%"
		args = append(args, like)
		idx := len(args)
		clauses = append(clauses, fmt.Sprintf(`(
			lower(s.primary_name) like $%[1]d
			or lower(coalesce(s.biography, '')) like $%[1]d
			or exists (select 1 from saint_aliases sa where sa.saint_id = s.id and lower(sa.alias) like $%[1]d)
			or exists (select 1 from patronage_saint ps2 join patronages p2 on p2.id = ps2.patronage_id where ps2.saint_id = s.id and lower(p2.name) like $%[1]d)
			or exists (select 1 from religious_order_saint ros2 join religious_orders ro2 on ro2.id = ros2.religious_order_id where ros2.saint_id = s.id and lower(ro2.name) like $%[1]d)
		)`, idx))
	}
	if filters.Type != "" {
		add("s.canonical_status = $%d", filters.Type)
	}
	if filters.Patronage != "" {
		add("exists (select 1 from patronage_saint ps3 join patronages p3 on p3.id = ps3.patronage_id where ps3.saint_id = s.id and p3.slug = $%d)", filters.Patronage)
	}
	if filters.Order != "" {
		add("exists (select 1 from religious_order_saint ros3 join religious_orders ro3 on ro3.id = ros3.religious_order_id where ros3.saint_id = s.id and ro3.slug = $%d)", filters.Order)
	}
	if filters.FeastMonth >= 1 && filters.FeastMonth <= 12 {
		add("exists (select 1 from feast_days fd3 where fd3.saint_id = s.id and fd3.month = $%d)", filters.FeastMonth)
	}
	if filters.FeastDay >= 1 && filters.FeastDay <= 31 {
		add("exists (select 1 from feast_days fd4 where fd4.saint_id = s.id and fd4.day = $%d)", filters.FeastDay)
	}
	return " where " + strings.Join(clauses, " and "), args
}

func saintSearchJoins(filters SearchFilters) string {
	return ""
}

func saintOrderBy(sort, direction string) string {
	if direction != "desc" {
		direction = "asc"
	}
	switch sort {
	case "birth_year":
		return "order by s.birth_year " + direction + " nulls last, s.primary_name asc"
	case "death_year":
		return "order by s.death_year " + direction + " nulls last, s.primary_name asc"
	case "feast_day":
		return "order by min(fd.month * 100 + fd.day) " + direction + " nulls last, s.primary_name asc"
	default:
		return "order by s.primary_name " + direction
	}
}

func parseLocations(raw []byte) []Location {
	var out []Location
	if len(raw) == 0 || json.Unmarshal(raw, &out) != nil {
		return []Location{}
	}
	return out
}

func parseTitles(raw []byte) []string {
	var generic []any
	if len(raw) == 0 || json.Unmarshal(raw, &generic) != nil {
		return []string{}
	}
	out := []string{}
	for _, item := range generic {
		switch value := item.(type) {
		case string:
			out = append(out, value)
		case map[string]any:
			if label, ok := value["label"].(string); ok && label != "" {
				out = append(out, label)
			} else if role, ok := value["role"].(string); ok && role != "" {
				out = append(out, role)
			}
		}
	}
	return out
}
