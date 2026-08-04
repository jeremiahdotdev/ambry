package database

import "testing"

func TestNormalizePagination(t *testing.T) {
	page, perPage := NormalizePagination(0, 0)
	if page != 1 || perPage != 20 {
		t.Fatalf("expected defaults, got page=%d perPage=%d", page, perPage)
	}

	page, perPage = NormalizePagination(2, 500)
	if page != 2 || perPage != 100 {
		t.Fatalf("expected clamped perPage, got page=%d perPage=%d", page, perPage)
	}
}

func TestNewPagination(t *testing.T) {
	pagination := NewPagination(2, 20, 41)
	if pagination.TotalPages != 3 {
		t.Fatalf("expected 3 total pages, got %d", pagination.TotalPages)
	}
}
