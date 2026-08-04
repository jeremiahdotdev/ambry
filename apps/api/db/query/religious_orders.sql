-- name: CountReligiousOrders :one
select count(*)
from religious_orders
where lower(name) like $1
   or lower(coalesce(description, '')) like $1
   or lower(coalesce(abbreviation, '')) like $1;

-- name: ListReligiousOrders :many
select ro.id, ro.name, ro.slug, ro.abbreviation, ro.description, count(ros.saint_id)::int as saint_count
from religious_orders ro
left join religious_order_saint ros on ros.religious_order_id = ro.id
where lower(ro.name) like $1
   or lower(coalesce(ro.description, '')) like $1
   or lower(coalesce(ro.abbreviation, '')) like $1
group by ro.id
order by ro.name
limit $2 offset $3;
