package patronage

import "api/internal/database"

type PatronageItem struct {
	ID          string  `json:"id" doc:"Patronage UUID."`
	Name        string  `json:"name" doc:"Patronage name." example:"Ireland"`
	Slug        string  `json:"slug" doc:"Stable slug." example:"ireland"`
	Category    *string `json:"category,omitempty" doc:"Optional category."`
	Description *string `json:"description,omitempty" doc:"Optional description."`
	SaintCount  int     `json:"saint_count" doc:"Number of associated saints." example:"12"`
}

type Filters struct {
	Query   string
	Page    int
	PerPage int
}

type PatronagePage struct {
	Data       []PatronageItem     `json:"data"`
	Pagination database.Pagination `json:"pagination"`
}
