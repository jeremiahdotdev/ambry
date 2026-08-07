package bibleverse

import (
	"context"
	"net/http"

	"github.com/danielgtaylor/huma/v2"
)

type listVersesInput struct {
	Query    string   `query:"q" doc:"Case-insensitive search across book and verse text." example:"beginning"`
	Book     BookName `query:"book" doc:"Case-insensitive exact book name filter." example:"Genesis" enum:"Genesis,Exodus,Leviticus,Numbers,Deuteronomy,Joshua,Judges,Ruth,1 Samuel,2 Samuel,1 Kings,2 Kings,1 Chronicles,2 Chronicles,Ezra,Nehemiah,Tobit,Judith,Esther,Job,Psalms,Proverbs,Ecclesiastes,Song of Songs,Wisdom,Sirach,Isaiah,Jeremiah,Lamentations,Baruch,Ezekiel,Daniel,Hosea,Joel,Amos,Obadiah,Jonah,Micah,Nahum,Habakkuk,Zephaniah,Haggai,Zechariah,Malachi,1 Maccabees,2 Maccabees,Matthew,Mark,Luke,John,Acts,Romans,1 Corinthians,2 Corinthians,Galatians,Ephesians,Philippians,Colossians,1 Thessalonians,2 Thessalonians,1 Timothy,2 Timothy,Titus,Philemon,Hebrews,James,1 Peter,2 Peter,1 John,2 John,3 John,Jude,Revelation"`
	BookCode BookCode `query:"book_code" doc:"Case-insensitive exact book code filter." example:"gen" enum:"gen,exo,lev,num,deu,jos,jdg,rut,1sa,2sa,1ki,2ki,1ch,2ch,ezr,neh,tob,jth,est,job,psa,pro,ecc,son,wis,sir,isa,jer,lam,bar,eze,dan,hos,joe,amo,oba,jon,mic,nah,hab,zep,hag,zec,mal,1ma,2ma,mat,mar,luk,joh,act,rom,1co,2co,gal,eph,phi,col,1th,2th,1ti,2ti,tit,phm,heb,jam,1pe,2pe,1jo,2jo,3jo,jud,rev"`
	Chapter  int      `query:"chapter" doc:"Optional chapter filter." example:"1"`
	Verse    int      `query:"verse" doc:"Optional verse-number filter." example:"1"`
	Page     int      `query:"page" minimum:"1" default:"1" doc:"Page number."`
	PerPage  int      `query:"per_page" minimum:"1" maximum:"100" default:"20" doc:"Results per page."`
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
		Security:    []map[string][]string{{"BearerAuth": {}}},
	}, func(ctx context.Context, input *listVersesInput) (*listVersesOutput, error) {
		if input.Chapter < 0 {
			return nil, huma.Error400BadRequest("chapter must be greater than 0")
		}
		if input.Verse < 0 {
			return nil, huma.Error400BadRequest("verse must be greater than 0")
		}
		page, err := service.List(ctx, Filters{
			Query:    input.Query,
			Book:     string(input.Book),
			BookCode: string(input.BookCode),
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
