package saint

import (
	"encoding/json"
	"errors"
	"net/http"
	"testing"

	"github.com/danielgtaylor/huma/v2/humatest"
)

func TestOfTheDayHandler(t *testing.T) {
	for _, tc := range []struct {
		name   string
		date   string
		err    error
		status int
	}{
		{"success", "2026-10-11", nil, http.StatusOK},
		{"invalid date", "2026-02-29", nil, http.StatusBadRequest},
		{"exhausted", "2026-10-11", ErrNoAvailableSaint, http.StatusConflict},
		{"database error", "2026-10-11", errors.New("private database details"), http.StatusInternalServerError},
	} {
		t.Run(tc.name, func(t *testing.T) {
			repo := &fakeRepo{selection: OfTheDay{Date: tc.date, Saint: Detail{Slug: "st-test"}}, err: tc.err}
			_, api := humatest.New(t)
			RegisterRoutes(api, NewService(repo))
			response := api.Get("/api/v1/saint-of-the-day?date=" + tc.date)
			if response.Code != tc.status {
				t.Fatalf("expected %d, got %d: %s", tc.status, response.Code, response.Body.String())
			}
			if tc.status == http.StatusOK {
				var selection OfTheDay
				if err := json.Unmarshal(response.Body.Bytes(), &selection); err != nil {
					t.Fatal(err)
				}
				if selection.Date != tc.date || selection.Saint.Slug != "st-test" {
					t.Fatalf("unexpected response: %+v", selection)
				}
			}
		})
	}
}

// Ensure the endpoint defaults its date before calling the repository.
func TestOfTheDayHandlerDefaultDate(t *testing.T) {
	repo := &fakeRepo{}
	_, api := humatest.New(t)
	RegisterRoutes(api, NewService(repo))
	response := api.Get("/api/v1/saint-of-the-day")
	if response.Code != http.StatusOK || repo.selectedDay.IsZero() {
		t.Fatalf("expected today's selection, got %d, %v", response.Code, repo.selectedDay)
	}
}

// Compile-time check that both the fake and PostgreSQL implementation implement
// the complete repository contract used by the HTTP API.
var _ Repository = (*fakeRepo)(nil)
var _ Repository = PostgresRepository{}
