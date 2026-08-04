package feastday

import "context"

type Repository interface {
	List(context.Context, Filters) (FeastDayPage, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) List(ctx context.Context, filters Filters) (FeastDayPage, error) {
	return s.repo.List(ctx, filters)
}
