package feastday

import (
	"context"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
)

type listFeastDaysInput struct {
	Month   int `query:"month" doc:"Optional feast month, 1 through 12." example:"3"`
	Day     int `query:"day" doc:"Optional feast day, 1 through 31." example:"17"`
	Page    int `query:"page" minimum:"1" default:"1" doc:"Page number."`
	PerPage int `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page."`
}

type listFeastDaysOutput struct {
	Body FeastDayPage
}

func RegisterRoutes(api huma.API, service Service) {
	huma.Register(api, huma.Operation{
		OperationID: "list-feast-days",
		Method:      http.MethodGet,
		Path:        "/api/v1/feast-days",
		Summary:     "List feast-day calendar entries",
		Description: "Returns saints associated with matching feast days.",
		Tags:        []string{"Feast Days"},
	}, func(ctx context.Context, input *listFeastDaysInput) (*listFeastDaysOutput, error) {
		if input.Month < 0 || input.Month > 12 {
			return nil, huma.Error400BadRequest("month must be between 1 and 12")
		}
		if input.Day < 0 || input.Day > 31 {
			return nil, huma.Error400BadRequest("day must be between 1 and 31")
		}
		page, err := service.List(ctx, Filters{Month: input.Month, Day: input.Day, Page: input.Page, PerPage: input.PerPage})
		if err != nil {
			return nil, huma.Error500InternalServerError("An unexpected error occurred.")
		}
		return &listFeastDaysOutput{Body: page}, nil
	})
}
