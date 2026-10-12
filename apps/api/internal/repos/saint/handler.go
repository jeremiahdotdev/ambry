package saint

import (
	"context"
	"errors"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
)

type listSaintsInput struct {
	Query      string `query:"q" doc:"Case-insensitive search text." example:"Patrick"`
	Type       string `query:"type" doc:"Canonical status filter." example:"saint"`
	Patronage  string `query:"patronage" doc:"Patronage slug filter." example:"ireland"`
	Order      string `query:"order" doc:"Religious order slug filter." example:"benedictines"`
	FeastMonth int    `query:"feast_month" doc:"Optional feast month filter, 1 through 12." example:"3"`
	Page       int    `query:"page" minimum:"1" default:"1" doc:"Page number." example:"1"`
	PerPage    int    `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page." example:"20"`
	Sort       string `query:"sort" enum:"name,birth_year,death_year,feast_day" default:"name" doc:"Sort field." example:"name"`
	Direction  string `query:"direction" enum:"asc,desc" default:"asc" doc:"Sort direction." example:"asc"`
}

type listSaintsOutput struct {
	Body SearchPage
}

type getSaintInput struct {
	Slug string `path:"slug" minLength:"1" doc:"Saint slug." example:"st-patrick"`
}

type getSaintOutput struct {
	Body Detail
}

type getSaintOfTheDayInput struct {
	Date string `query:"date" doc:"Optional YYYY-MM-DD date (years 0001–9999). Defaults to today in UTC." example:"2026-10-11"`
}

type getSaintOfTheDayOutput struct {
	Body OfTheDay
}

func RegisterRoutes(api huma.API, service Service) {
	huma.Register(api, huma.Operation{
		OperationID: "get-saint-of-the-day",
		Method:      http.MethodGet,
		Path:        "/api/v1/saint-of-the-day",
		Summary:     "Get saint of the day",
		Description: "Returns the saved saint selection for a date, shared by all API consumers. The first request persists a selection, preferring an unused canonically designated saint with a matching feast day and falling back to any unused saint. A saint cannot be selected on two dates in the same calendar year. Returns 409 if no unused saints remain.",
		Tags:        []string{"Saints"},
		Security:    []map[string][]string{{"BearerAuth": {}}},
		Errors:      []int{http.StatusBadRequest, http.StatusConflict, http.StatusInternalServerError},
	}, func(ctx context.Context, input *getSaintOfTheDayInput) (*getSaintOfTheDayOutput, error) {
		selection, err := service.GetOfTheDay(ctx, input.Date)
		if err != nil {
			return nil, handlerError(err)
		}
		return &getSaintOfTheDayOutput{Body: selection}, nil
	})

	huma.Register(api, huma.Operation{
		OperationID: "list-saints",
		Method:      http.MethodGet,
		Path:        "/api/v1/saints",
		Summary:     "List and search saints",
		Description: "Search saints by name, alias, biography, patronage, or religious order. Uses safe parameterized PostgreSQL queries and avoids duplicate saints from joins.",
		Tags:        []string{"Saints"},
		Security:    []map[string][]string{{"BearerAuth": {}}},
	}, func(ctx context.Context, input *listSaintsInput) (*listSaintsOutput, error) {
		if input.FeastMonth < 0 || input.FeastMonth > 12 {
			return nil, badRequest("feast_month must be between 1 and 12")
		}
		page, err := service.Search(ctx, SearchFilters{
			Query:      input.Query,
			Type:       input.Type,
			Patronage:  input.Patronage,
			Order:      input.Order,
			FeastMonth: input.FeastMonth,
			Page:       input.Page,
			PerPage:    input.PerPage,
			Sort:       input.Sort,
			Direction:  input.Direction,
		})
		if err != nil {
			return nil, handlerError(err)
		}
		return &listSaintsOutput{Body: page}, nil
	})

	huma.Register(api, huma.Operation{
		OperationID: "get-saint-by-slug",
		Method:      http.MethodGet,
		Path:        "/api/v1/saints/{slug}",
		Summary:     "Get saint by slug",
		Description: "Returns a full saint record and related aliases, feast days, patronages, religious orders, and JSON-backed profile locations/titles when available.",
		Tags:        []string{"Saints"},
		Security:    []map[string][]string{{"BearerAuth": {}}},
	}, func(ctx context.Context, input *getSaintInput) (*getSaintOutput, error) {
		detail, err := service.GetBySlug(ctx, input.Slug)
		if err != nil {
			return nil, handlerError(err)
		}
		return &getSaintOutput{Body: detail}, nil
	})
}

func handlerError(err error) error {
	if errors.Is(err, ErrInvalidDate) {
		return huma.Error400BadRequest(ErrInvalidDate.Error())
	}
	if errors.Is(err, ErrNoAvailableSaint) {
		return huma.Error409Conflict("No unused saints remain for the requested year.")
	}
	if errors.Is(err, ErrNotFound) {
		return huma.Error404NotFound("The requested saint was not found.")
	}
	return huma.Error500InternalServerError("An unexpected error occurred.")
}

func badRequest(message string) error {
	return huma.Error400BadRequest(message)
}
