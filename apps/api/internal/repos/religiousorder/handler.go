package religiousorder

import (
	"context"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
)

type listOrdersInput struct {
	Query   string `query:"q" doc:"Case-insensitive search text." example:"Benedictine"`
	Page    int    `query:"page" minimum:"1" default:"1" doc:"Page number."`
	PerPage int    `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page."`
}

type listOrdersOutput struct {
	Body ReligiousOrderPage
}

func RegisterRoutes(api huma.API, service Service) {
	huma.Register(api, huma.Operation{
		OperationID: "list-religious-orders",
		Method:      http.MethodGet,
		Path:        "/api/v1/religious-orders",
		Summary:     "List religious orders",
		Description: "Lists religious orders with associated saint counts.",
		Tags:        []string{"Religious Orders"},
		Security:    []map[string][]string{{"BearerAuth": {}}},
	}, func(ctx context.Context, input *listOrdersInput) (*listOrdersOutput, error) {
		page, err := service.List(ctx, Filters{Query: input.Query, Page: input.Page, PerPage: input.PerPage})
		if err != nil {
			return nil, huma.Error500InternalServerError("An unexpected error occurred.")
		}
		return &listOrdersOutput{Body: page}, nil
	})
}
