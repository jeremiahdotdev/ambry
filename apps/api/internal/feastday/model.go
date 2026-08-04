package feastday

import (
	"github.com/jeremiahdotdev/ambry/apps/api/internal/database"
	"github.com/jeremiahdotdev/ambry/apps/api/internal/saint"
)

type Filters struct {
	Month   int
	Day     int
	Page    int
	PerPage int
}

type FeastDayPage struct {
	Data       []saint.SearchResult `json:"data"`
	Pagination database.Pagination  `json:"pagination"`
}
