package saint

import (
	"context"
	"errors"
	"strings"
)

var ErrNotFound = errors.New("saint not found")

type Repository interface {
	Search(context.Context, SearchFilters) (SearchPage, error)
	GetBySlug(context.Context, string) (Detail, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) Search(ctx context.Context, filters SearchFilters) (SearchPage, error) {
	filters.Sort = normalizeSort(filters.Sort)
	filters.Direction = normalizeDirection(filters.Direction)
	return s.repo.Search(ctx, filters)
}

func (s Service) GetBySlug(ctx context.Context, slug string) (Detail, error) {
	slug = strings.TrimSpace(slug)
	if slug == "" {
		return Detail{}, ErrNotFound
	}
	return s.repo.GetBySlug(ctx, slug)
}

func normalizeSort(sort string) string {
	switch strings.ToLower(strings.TrimSpace(sort)) {
	case "birth_year", "death_year", "feast_day":
		return strings.ToLower(strings.TrimSpace(sort))
	default:
		return "name"
	}
}

func normalizeDirection(direction string) string {
	if strings.ToLower(strings.TrimSpace(direction)) == "desc" {
		return "desc"
	}
	return "asc"
}
