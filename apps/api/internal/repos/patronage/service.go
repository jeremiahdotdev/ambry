package patronage

import "context"

type Repository interface {
	List(context.Context, Filters) (PatronagePage, error)
}

type Service struct {
	repo Repository
}

func NewService(repo Repository) Service {
	return Service{repo: repo}
}

func (s Service) List(ctx context.Context, filters Filters) (PatronagePage, error) {
	return s.repo.List(ctx, filters)
}
