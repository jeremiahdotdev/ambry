package feastday

import (
	"context"
	"time"

	"api/internal/repos/saint"
	"github.com/jackc/pgx/v5/pgxpool"
)

type PostgresRepository struct {
	saints saint.PostgresRepository
}

func NewPostgresRepository(pool *pgxpool.Pool, queryTimeout time.Duration) PostgresRepository {
	return PostgresRepository{saints: saint.NewPostgresRepository(pool, queryTimeout)}
}

func (r PostgresRepository) List(ctx context.Context, filters Filters) (FeastDayPage, error) {
	result, err := r.saints.Search(ctx, saint.SearchFilters{
		FeastMonth: filters.Month,
		FeastDay:   filters.Day,
		Page:       filters.Page,
		PerPage:    filters.PerPage,
		Sort:       "feast_day",
		Direction:  "asc",
	})
	if err != nil {
		return FeastDayPage{}, err
	}
	return FeastDayPage{Data: result.Data, Pagination: result.Pagination}, nil
}
