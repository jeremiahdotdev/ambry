package religiousorder

import "github.com/jeremiahdotdev/ambry/apps/api/internal/database"

type ReligiousOrderItem struct {
	ID           string  `json:"id" doc:"Religious order UUID."`
	Name         string  `json:"name" doc:"Religious order name."`
	Slug         string  `json:"slug" doc:"Stable slug."`
	Abbreviation *string `json:"abbreviation,omitempty" doc:"Common abbreviation."`
	Description  *string `json:"description,omitempty" doc:"Optional description."`
	SaintCount   int     `json:"saint_count" doc:"Number of associated saints."`
}

type Filters struct {
	Query   string
	Page    int
	PerPage int
}

type ReligiousOrderPage struct {
	Data       []ReligiousOrderItem `json:"data"`
	Pagination database.Pagination  `json:"pagination"`
}
