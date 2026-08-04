-- name: GetSaintBySlug :one
select
    id,
    primary_name,
    slug,
    canonical_status,
    biography,
    birth_year,
    death_year,
    profile_landmarks,
    profile_church_roles,
    roles
from saints
where slug = $1;

-- name: ListSaintAliases :many
select alias
from saint_aliases
where saint_id = $1
order by alias;

-- name: ListSaintFeastDays :many
select month, day, calendar
from feast_days
where saint_id = $1
order by month, day, calendar;

-- name: ListSaintPatronages :many
select p.name
from patronages p
join patronage_saint ps on ps.patronage_id = p.id
where ps.saint_id = $1
order by p.name;

-- name: ListSaintReligiousOrders :many
select ro.name
from religious_orders ro
join religious_order_saint ros on ros.religious_order_id = ro.id
where ros.saint_id = $1
order by ro.name;
