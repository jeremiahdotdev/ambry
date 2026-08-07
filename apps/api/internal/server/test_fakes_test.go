package server

import (
	"context"

	"api/internal/database"
	"api/internal/repos/bibleverse"
	"api/internal/repos/feastday"
	"api/internal/repos/patronage"
	"api/internal/repos/religiousorder"
	"api/internal/repos/saint"
)

type okHealth struct{}

func (okHealth) Ping(context.Context) error { return nil }

type saintRepoFake struct{}

func (saintRepoFake) Search(context.Context, saint.SearchFilters) (saint.SearchPage, error) {
	return saint.SearchPage{
		Data: []saint.SearchResult{{
			ID:              "00000000-0000-0000-0000-000000000001",
			PrimaryName:     "St. Test",
			Slug:            "st-test",
			CanonicalStatus: "saint",
			FeastDays:       []saint.FeastDay{},
			Patronages:      []string{},
			ReligiousOrders: []string{},
		}},
		Pagination: database.NewPagination(1, 20, 1),
	}, nil
}

func (saintRepoFake) GetBySlug(context.Context, string) (saint.Detail, error) {
	return saint.Detail{}, saint.ErrNotFound
}

type emptyPatronageRepo struct{}

func (emptyPatronageRepo) List(context.Context, patronage.Filters) (patronage.PatronagePage, error) {
	return patronage.PatronagePage{Data: []patronage.PatronageItem{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type emptyOrderRepo struct{}

func (emptyOrderRepo) List(context.Context, religiousorder.Filters) (religiousorder.ReligiousOrderPage, error) {
	return religiousorder.ReligiousOrderPage{Data: []religiousorder.ReligiousOrderItem{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type emptyFeastRepo struct{}

func (emptyFeastRepo) List(context.Context, feastday.Filters) (feastday.FeastDayPage, error) {
	return feastday.FeastDayPage{Data: []saint.SearchResult{}, Pagination: database.NewPagination(1, 20, 0)}, nil
}

type bibleVerseRepoFake struct{}

func (bibleVerseRepoFake) List(context.Context, bibleverse.Filters) (bibleverse.VersePage, error) {
	return bibleverse.VersePage{
		Data: []bibleverse.Verse{{
			ID:        "newadvent-bible-gen-001-001",
			Book:      "Genesis",
			BookCode:  "gen",
			BookOrder: 1,
			Chapter:   1,
			Verse:     1,
		}},
		Pagination: database.NewPagination(1, 20, 1),
	}, nil
}
