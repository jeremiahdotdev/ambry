.PHONY: dev build test test-integration lint sqlc docker-up docker-down

dev:
	go run ./cmd/api

build:
	go build -o bin/ambry-api ./cmd/api

test:
	go test ./...

test-integration:
	go test -tags=integration ./...

lint:
	go vet ./...

sqlc:
	sqlc generate

docker-up:
	docker compose up --build

docker-down:
	docker compose down -v
