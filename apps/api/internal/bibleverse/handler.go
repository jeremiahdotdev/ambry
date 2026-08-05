package bibleverse

import (
	"context"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
)

type listVersesInput struct {
	Query    string `query:"q" doc:"Case-insensitive search across book and verse text." example:"beginning"`
	Book     string `query:"book" doc:"Case-insensitive exact book name filter." example:"Genesis"`
	BookCode string `query:"book_code" doc:"Case-insensitive exact book code filter." example:"gen"`
	Chapter  int    `query:"chapter" doc:"Optional chapter filter." example:"1"`
	Verse    int    `query:"verse" doc:"Optional verse-number filter." example:"1"`
	Page     int    `query:"page" minimum:"1" default:"1" doc:"Page number."`
	PerPage  int    `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page."`
}

type listVersesOutput struct {
	Body VersePage
}

func RegisterRoutes(api huma.API, service Service) {
	huma.Register(api, huma.Operation{
		OperationID: "list-bible-verses",
		Method:      http.MethodGet,
		Path:        "/api/v1/bible-verses",
		Summary:     "List Bible verses",
		Description: "Lists Bible verses with optional filters for book, book code, chapter, verse number, and text search.",
		Tags:        []string{"Bible Verses"},
	}, func(ctx context.Context, input *listVersesInput) (*listVersesOutput, error) {
		if input.Chapter < 0 {
			return nil, huma.Error400BadRequest("chapter must be greater than 0")
		}
		if input.Verse < 0 {
			return nil, huma.Error400BadRequest("verse must be greater than 0")
		}
		page, err := service.List(ctx, Filters{
			Query:    input.Query,
			Book:     input.Book,
			BookCode: input.BookCode,
			Chapter:  input.Chapter,
			Verse:    input.Verse,
			Page:     input.Page,
			PerPage:  input.PerPage,
		})
		if err != nil {
			return nil, huma.Error500InternalServerError("An unexpected error occurred.")
		}
		return &listVersesOutput{Body: page}, nil
	})
}
