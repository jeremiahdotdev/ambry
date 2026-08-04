package feastday

import (
	"api/internal/database"
	"api/internal/saint"
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
