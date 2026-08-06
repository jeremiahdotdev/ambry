package patronage

import (
	"context"
	"net/http"

	"api/internal/apidocs"
	"github.com/danielgtaylor/huma/v2"
)

type listPatronagesInput struct {
	Query   string `query:"q" doc:"Case-insensitive search text." example:"Ireland"`
	Page    int    `query:"page" minimum:"1" default:"1" doc:"Page number."`
	PerPage int    `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page."`
}

type listPatronagesOutput struct {
	Body PatronagePage
}

func RegisterRoutes(api huma.API, service Service) {
	huma.Register(api, huma.Operation{
		OperationID: "list-patronages",
		Method:      http.MethodGet,
		Path:        "/api/v1/patronages",
		Summary:     "List patronages",
		Description: apidocs.Text(apidocs.Content.Operations.ListPatronages),
		Tags:        []string{"Patronages"},
		Security:    []map[string][]string{{"BearerAuth": {}}},
	}, func(ctx context.Context, input *listPatronagesInput) (*listPatronagesOutput, error) {
		page, err := service.List(ctx, Filters{Query: input.Query, Page: input.Page, PerPage: input.PerPage})
		if err != nil {
			return nil, huma.Error500InternalServerError("An unexpected error occurred.")
		}
		return &listPatronagesOutput{Body: page}, nil
	})
}
