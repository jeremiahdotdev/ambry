package bibleverse

import "context"

type Repository interface {
	List(context.Context, Filters) (VersePage, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) List(ctx context.Context, filters Filters) (VersePage, error) {
	return s.repo.List(ctx, filters)
}
