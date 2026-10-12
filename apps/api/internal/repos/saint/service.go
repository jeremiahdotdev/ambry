package saint

import (
	"context"
	"errors"
	"strings"
	"time"
)

var ErrNotFound = errors.New("saint not found")
var ErrInvalidDate = errors.New("date must be a valid YYYY-MM-DD date with year between 0001 and 9999")
var ErrNoAvailableSaint = errors.New("no unused saints remain for this year")

type Repository interface {
	Search(context.Context, SearchFilters) (SearchPage, error)
	GetBySlug(context.Context, string) (Detail, error)
	GetOfTheDay(context.Context, time.Time) (OfTheDay, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) GetOfTheDay(ctx context.Context, date string) (OfTheDay, error) {
	if date == "" {
		date = time.Now().UTC().Format(time.DateOnly)
	}
	day, err := time.Parse(time.DateOnly, date)
	if err != nil || day.Year() < 1 || day.Year() > 9999 {
		return OfTheDay{}, ErrInvalidDate
	}
	return s.repo.GetOfTheDay(ctx, day)
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
