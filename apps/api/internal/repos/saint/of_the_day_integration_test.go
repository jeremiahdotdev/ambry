//go:build integration

package saint

import (
	"context"
	"errors"
	"fmt"
	"net"
	"os"
	"sync"
	"testing"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"
	"github.com/jackc/pgx/v5/pgxpool"
)

func dayTestPool(t *testing.T) *pgxpool.Pool {
	t.Helper()
	url := os.Getenv("TEST_DATABASE_URL")
	if url == "" {
		t.Skip("TEST_DATABASE_URL is required for local PostgreSQL integration tests")
	}
	cfg, err := pgxpool.ParseConfig(url)
	if err != nil {
		t.Fatal(err)
	}
	for _, host := range append([]string{cfg.ConnConfig.Host}, fallbackHosts(cfg.ConnConfig.Fallbacks)...) {
		ip := net.ParseIP(host)
		if host != "localhost" && (ip == nil || !ip.IsLoopback()) {
			t.Fatal("refusing to run integration tests against a non-loopback database")
		}
	}
	ctx := context.Background()
	admin, err := pgxpool.NewWithConfig(ctx, cfg.Copy())
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(admin.Close)
	schema := pgx.Identifier{fmt.Sprintf("saint_day_test_%d", time.Now().UnixNano())}.Sanitize()
	if _, err := admin.Exec(ctx, "create schema "+schema); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() {
		if _, err := admin.Exec(ctx, "drop schema "+schema+" cascade"); err != nil {
			t.Errorf("cleaning test schema: %v", err)
		}
	})
	cfg.ConnConfig.RuntimeParams["search_path"] = schema
	cfg.MaxConns = 10
	pool, err := pgxpool.NewWithConfig(ctx, cfg)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(pool.Close)
	return pool
}

func fallbackHosts(fallbacks []*pgconn.FallbackConfig) []string {
	hosts := make([]string, 0, len(fallbacks))
	for _, fallback := range fallbacks {
		hosts = append(hosts, fallback.Host)
	}
	return hosts
}

func dayTestRepository(t *testing.T, count int) (PostgresRepository, *pgxpool.Pool) {
	t.Helper()
	pool := dayTestPool(t)
	schema, err := os.ReadFile("../../../db/schema/schema.sql")
	if err != nil {
		t.Fatal(err)
	}
	ctx := context.Background()
	if _, err := pool.Exec(ctx, string(schema)); err != nil {
		t.Fatal(err)
	}
	for i := 1; i <= count; i++ {
		id := fmt.Sprintf("00000000-0000-0000-0000-%012d", i)
		if _, err := pool.Exec(ctx, "insert into saints (id, primary_name, slug) values ($1, $2, $3)", id, fmt.Sprintf("St. Test %d", i), fmt.Sprintf("st-test-%d", i)); err != nil {
			t.Fatal(err)
		}
	}
	return NewPostgresRepository(pool, 10*time.Second), pool
}

func getDay(t *testing.T, repo PostgresRepository, date string) OfTheDay {
	t.Helper()
	day, err := time.Parse(time.DateOnly, date)
	if err != nil {
		t.Fatal(err)
	}
	selection, err := repo.GetOfTheDay(context.Background(), day)
	if err != nil {
		t.Fatal(err)
	}
	return selection
}

func TestOfTheDayPersistencePreferenceAndYearReset(t *testing.T) {
	repo, pool := dayTestRepository(t, 2)
	ctx := context.Background()
	if _, err := pool.Exec(ctx, "insert into feast_days (saint_id, month, day) values ('00000000-0000-0000-0000-000000000001', 10, 11), ('00000000-0000-0000-0000-000000000001', 10, 12)"); err != nil {
		t.Fatal(err)
	}
	imageURL := "https://images.example.com/st-test-1/portrait.webp"
	if _, err := pool.Exec(ctx, "update saints set image_portrait_url = $1 where slug = 'st-test-1'", imageURL); err != nil {
		t.Fatal(err)
	}
	first := getDay(t, repo, "2026-10-11")
	if first.Saint.Slug != "st-test-1" || len(first.Saint.FeastDays) != 2 || first.Saint.ImagePortraitURL == nil || *first.Saint.ImagePortraitURL != imageURL {
		t.Fatalf("expected feast-day saint with full profile: %+v", first)
	}
	if repeat := getDay(t, repo, first.Date); repeat.Saint.ID != first.Saint.ID {
		t.Fatalf("repeat changed saved selection: %+v", repeat)
	}
	second := getDay(t, repo, "2026-10-12")
	if second.Saint.ImagePortraitURL != nil {
		t.Fatal("expected missing portrait to remain nil")
	}
	if second.Saint.ID == first.Saint.ID {
		t.Fatal("reused feast-day saint instead of unused fallback")
	}
	day, _ := time.Parse(time.DateOnly, "2026-10-13")
	if _, err := repo.GetOfTheDay(ctx, day); !errors.Is(err, ErrNoAvailableSaint) {
		t.Fatalf("expected exhaustion, got %v", err)
	}
	if repeat := getDay(t, repo, first.Date); repeat.Saint.ID != first.Saint.ID {
		t.Fatal("saved selection unavailable after exhaustion")
	}
	if nextYear := getDay(t, repo, "2027-10-11"); nextYear.Saint.ID != first.Saint.ID {
		t.Fatal("expected same saint to be eligible in a new year")
	}
	var count int
	if err := pool.QueryRow(ctx, "select count(*) from saint_of_the_day").Scan(&count); err != nil || count != 3 {
		t.Fatalf("expected exactly three persisted selections, got %d, %v", count, err)
	}
}

func TestOfTheDayConcurrentRequests(t *testing.T) {
	repo, pool := dayTestRepository(t, 8)
	var wg sync.WaitGroup
	results := make(chan OfTheDay, 23)
	errorsCh := make(chan error, 23)
	start := make(chan struct{})
	for i := 0; i < 23; i++ {
		date := "2028-02-29"
		if i >= 16 {
			date = fmt.Sprintf("2028-03-%02d", i-15)
		}
		day, _ := time.Parse(time.DateOnly, date)
		wg.Add(1)
		go func() {
			defer wg.Done()
			<-start
			selection, err := repo.GetOfTheDay(context.Background(), day)
			if err != nil {
				errorsCh <- err
				return
			}
			results <- selection
		}()
	}
	close(start)
	wg.Wait()
	close(results)
	close(errorsCh)
	for err := range errorsCh {
		t.Errorf("concurrent selection: %v", err)
	}
	byDate := map[string]string{}
	bySaint := map[string]string{}
	for result := range results {
		if previous, ok := byDate[result.Date]; ok && previous != result.Saint.ID {
			t.Fatal("same date returned different saints")
		}
		if previous, ok := bySaint[result.Saint.ID]; ok && previous != result.Date {
			t.Fatal("different dates returned the same saint in one year")
		}
		byDate[result.Date] = result.Saint.ID
		bySaint[result.Saint.ID] = result.Date
	}
	var count int
	if err := pool.QueryRow(context.Background(), "select count(*) from saint_of_the_day").Scan(&count); err != nil || count != 8 || len(byDate) != 8 {
		t.Fatalf("expected 8 selections, got %d rows and %d dates, %v", count, len(byDate), err)
	}
}

func TestOfTheDayEmptyOrIneligiblePool(t *testing.T) {
	repo, pool := dayTestRepository(t, 0)
	day, _ := time.Parse(time.DateOnly, "2026-10-11")
	for i := 0; i < 2; i++ {
		if _, err := repo.GetOfTheDay(context.Background(), day); !errors.Is(err, ErrNoAvailableSaint) {
			t.Fatalf("expected no eligible saint, got %v", err)
		}
		if _, err := pool.Exec(context.Background(), "insert into saints (id, primary_name, slug, canonical_status) values ('00000000-0000-0000-0000-000000000001', 'Bl. Test', 'bl-test', 'blessed') on conflict do nothing"); err != nil {
			t.Fatal(err)
		}
	}
}

func TestOfTheDayMigrationConstraints(t *testing.T) {
	pool := dayTestPool(t)
	ctx := context.Background()
	if _, err := pool.Exec(ctx, "create table saints (id uuid primary key); insert into saints values ('00000000-0000-0000-0000-000000000001'), ('00000000-0000-0000-0000-000000000002')"); err != nil {
		t.Fatal(err)
	}
	migration, err := os.ReadFile("../../../db/migrations/001_saint_of_the_day.sql")
	if err != nil {
		t.Fatal(err)
	}
	if _, err := pool.Exec(ctx, string(migration)); err != nil {
		t.Fatal(err)
	}
	if _, err := pool.Exec(ctx, "insert into saint_of_the_day (selection_date, saint_id) values ('2026-10-11', '00000000-0000-0000-0000-000000000001'), ('2027-10-11', '00000000-0000-0000-0000-000000000001')"); err != nil {
		t.Fatal(err)
	}
	for _, tc := range []struct{ sql, code string }{
		{"insert into saint_of_the_day (selection_date, saint_id) values ('2026-10-12', '00000000-0000-0000-0000-000000000001')", "23505"},
		{"insert into saint_of_the_day (selection_date, saint_id) values ('2026-10-11', '00000000-0000-0000-0000-000000000002')", "23505"},
		{"delete from saints where id = '00000000-0000-0000-0000-000000000001'", "23503"},
		{"insert into saint_of_the_day (selection_date, saint_id) values ('10000-01-01', '00000000-0000-0000-0000-000000000002')", "23514"},
	} {
		_, err := pool.Exec(ctx, tc.sql)
		var pgErr *pgconn.PgError
		if !errors.As(err, &pgErr) || pgErr.Code != tc.code {
			t.Fatalf("expected constraint error %s, got %v", tc.code, err)
		}
	}
}
