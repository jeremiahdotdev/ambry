package bibleverse

import "api/internal/database"

type Verse struct {
	ID          string  `json:"id" doc:"Stable verse ID." example:"newadvent-bible-gen-001-001"`
	Book        string  `json:"book" doc:"Bible book name." example:"Genesis"`
	BookCode    string  `json:"book_code" doc:"Short book code." example:"gen"`
	BookOrder   int     `json:"book_order" doc:"Canonical book order." example:"1"`
	Chapter     int     `json:"chapter" doc:"Chapter number." example:"1"`
	Verse       int     `json:"verse" doc:"Verse number." example:"1"`
	GreekText   *string `json:"greek_text,omitempty" doc:"Greek verse text when available."`
	EnglishText *string `json:"english_text,omitempty" doc:"English verse text when available."`
	LatinText   *string `json:"latin_text,omitempty" doc:"Latin verse text when available."`
}

type Filters struct {
	Query    string
	Book     string
	BookCode string
	Chapter  int
	Verse    int
	Page     int
	PerPage  int
}

type VersePage struct {
	Data       []Verse             `json:"data"`
	Pagination database.Pagination `json:"pagination"`
}
