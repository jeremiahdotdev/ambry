package saint

import (
	"context"
	"errors"
	"testing"
)

type fakeRepo struct {
	searchFilters SearchFilters
	detail        Detail
	err           error
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
