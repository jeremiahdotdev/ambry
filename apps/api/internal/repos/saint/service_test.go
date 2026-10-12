package saint

import (
	"context"
	"errors"
	"testing"
	"time"
)

type fakeRepo struct {
	searchFilters SearchFilters
	detail        Detail
	err           error
	selectedDay   time.Time
	selection     OfTheDay
}

func (f *fakeRepo) Search(ctx context.Context, filters SearchFilters) (SearchPage, error) {
	f.searchFilters = filters
	return SearchPage{}, f.err
}

func (f *fakeRepo) GetBySlug(ctx context.Context, slug string) (Detail, error) {
	if f.err != nil {
		return Detail{}, f.err
	}
	return f.detail, nil
}

func TestServiceNormalizesSearchSortAndDirection(t *testing.T) {
	repo := &fakeRepo{}
	service := NewService(repo)
	_, err := service.Search(context.Background(), SearchFilters{Sort: "unknown", Direction: "sideways"})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if repo.searchFilters.Sort != "name" || repo.searchFilters.Direction != "asc" {
		t.Fatalf("unexpected normalization: %+v", repo.searchFilters)
	}
}

func TestServiceBlankSlugIsNotFound(t *testing.T) {
	service := NewService(&fakeRepo{})
	_, err := service.GetBySlug(context.Background(), " ")
	if !errors.Is(err, ErrNotFound) {
		t.Fatalf("expected ErrNotFound, got %v", err)
	}
}

func (f *fakeRepo) GetOfTheDay(ctx context.Context, day time.Time) (OfTheDay, error) {
	f.selectedDay = day
	return f.selection, f.err
}

func TestServiceOfTheDayDate(t *testing.T) {
	for _, date := range []string{"2026-10-11", "2028-02-29", "0001-01-01", "9999-12-31"} {
		t.Run(date, func(t *testing.T) {
			expected := OfTheDay{Date: date, Saint: Detail{Slug: "st-test"}}
			repo := &fakeRepo{selection: expected}
			result, err := NewService(repo).GetOfTheDay(context.Background(), date)
			if err != nil || result.Date != expected.Date || result.Saint.Slug != expected.Saint.Slug {
				t.Fatalf("unexpected selection: %+v, %v", result, err)
			}
			if repo.selectedDay.Format(time.DateOnly) != date || repo.selectedDay.Location() != time.UTC {
				t.Fatalf("unexpected repository date: %v", repo.selectedDay)
			}
		})
	}
}

func TestServiceOfTheDayDefaultsToUTC(t *testing.T) {
	repo := &fakeRepo{}
	before := time.Now().UTC().Format(time.DateOnly)
	_, err := NewService(repo).GetOfTheDay(context.Background(), "")
	after := time.Now().UTC().Format(time.DateOnly)
	if err != nil {
		t.Fatal(err)
	}
	got := repo.selectedDay.Format(time.DateOnly)
	if got != before && got != after {
		t.Fatalf("expected today in UTC, got %s", got)
	}
}

func TestServiceOfTheDayRejectsInvalidDates(t *testing.T) {
	for _, date := range []string{"2026-02-29", "2026-04-31", "2026-13-01", "2026-1-01", "0000-01-01", "10000-01-01", "2026-10-11T00:00:00Z", " 2026-10-11", "today"} {
		t.Run(date, func(t *testing.T) {
			repo := &fakeRepo{}
			_, err := NewService(repo).GetOfTheDay(context.Background(), date)
			if !errors.Is(err, ErrInvalidDate) || !repo.selectedDay.IsZero() {
				t.Fatalf("expected validation before repository call, got %v, %v", err, repo.selectedDay)
			}
		})
	}
}

func TestServiceOfTheDayPropagatesRepositoryErrors(t *testing.T) {
	for _, expected := range []error{ErrNoAvailableSaint, errors.New("database unavailable")} {
		_, err := NewService(&fakeRepo{err: expected}).GetOfTheDay(context.Background(), "2026-10-11")
		if !errors.Is(err, expected) {
			t.Fatalf("expected %v, got %v", expected, err)
		}
	}
}
