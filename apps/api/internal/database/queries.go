package database

type Pagination struct {
	Page       int `json:"page" doc:"Current page number." example:"1"`
	PerPage    int `json:"per_page" doc:"Number of records returned per page." example:"20"`
	Total      int `json:"total" doc:"Total number of matching records." example:"1"`
	TotalPages int `json:"total_pages" doc:"Total page count." example:"1"`
}

func NormalizePagination(page, perPage int) (int, int) {
	if page < 1 {
		page = 1
	}
	if perPage < 1 {
		perPage = 20
	}
	if perPage > 100 {
		perPage = 100
	}
	return page, perPage
}

func NewPagination(page, perPage, total int) Pagination {
	totalPages := 0
	if total > 0 {
		totalPages = (total + perPage - 1) / perPage
	}
	return Pagination{Page: page, PerPage: perPage, Total: total, TotalPages: totalPages}
}
