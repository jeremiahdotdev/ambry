-- Apply explicitly before enabling the saint-of-the-day endpoint.
-- The Go API never applies migrations during startup or requests.
begin;

create table saint_of_the_day (
    selection_date date primary key,
    selection_year integer generated always as (extract(year from selection_date)::integer) stored,
    saint_id uuid not null references saints(id) on delete restrict,
    created_at timestamptz not null default now(),
    constraint saint_of_the_day_date_range check (
        selection_date >= date '0001-01-01' and selection_date < date '10000-01-01'
    ),
    constraint saint_of_the_day_year_saint_unique unique (selection_year, saint_id)
);

commit;
