package religiousorder

import "context"

type Repository interface {
	List(context.Context, Filters) (ReligiousOrderPage, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) List(ctx context.Context, filters Filters) (ReligiousOrderPage, error) {
	return s.repo.List(ctx, filters)
}
