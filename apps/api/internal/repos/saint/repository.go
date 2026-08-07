package saint

import (
	"context"
	_ "embed"
	"encoding/json"
	"errors"
	"strings"
	"time"

	"api/internal/database"
	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
)

//go:embed sql/search_count.sql
var searchCountSQL string

//go:embed sql/search_list.sql
var searchListSQL string

//go:embed sql/get_by_slug.sql
var getBySlugSQL string

//go:embed sql/list_aliases.sql
var listAliasesSQL string

//go:embed sql/list_patronages.sql
var listPatronagesSQL string

//go:embed sql/list_religious_orders.sql
var listReligiousOrdersSQL string

//go:embed sql/list_feast_days.sql
var listFeastDaysSQL string

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
	query, saintType, patronage, order, feastMonth, feastDay := searchParams(filters)

	var total int
	if err := r.pool.QueryRow(ctx, searchCountSQL, query, saintType, patronage, order, feastMonth, feastDay).Scan(&total); err != nil {
		return SearchPage{}, err
	}

	rows, err := r.pool.Query(ctx, searchListSQL, query, saintType, patronage, order, feastMonth, feastDay, perPage, (page-1)*perPage, filters.Sort, filters.Direction)
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
	err = tx.QueryRow(ctx, getBySlugSQL, slug).Scan(&detail.ID, &detail.PrimaryName, &detail.Slug, &detail.CanonicalStatus, &detail.Biography, &detail.BirthYear, &detail.DeathYear, &locationsJSON, &titlesJSON)
	if errors.Is(err, pgx.ErrNoRows) {
		return Detail{}, ErrNotFound
	}
	if err != nil {
		return Detail{}, err
	}

	detail.Aliases, err = queryStringList(ctx, tx, listAliasesSQL, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.Patronages, err = queryStringList(ctx, tx, listPatronagesSQL, detail.ID)
	if err != nil {
		return Detail{}, err
	}
	detail.ReligiousOrders, err = queryStringList(ctx, tx, listReligiousOrdersSQL, detail.ID)
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
	rows, err := q.Query(ctx, listFeastDaysSQL, saintID)
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

func searchParams(filters SearchFilters) (string, string, string, string, int, int) {
	query := strings.TrimSpace(filters.Query)
	if query != "" {
		query = "%" + strings.ToLower(query) + "%"
	}
	feastMonth := 0
	if filters.FeastMonth >= 1 && filters.FeastMonth <= 12 {
		feastMonth = filters.FeastMonth
	}
	feastDay := 0
	if filters.FeastDay >= 1 && filters.FeastDay <= 31 {
		feastDay = filters.FeastDay
	}
	return query,
		strings.TrimSpace(filters.Type),
		strings.TrimSpace(filters.Patronage),
		strings.TrimSpace(filters.Order),
		feastMonth,
		feastDay
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
