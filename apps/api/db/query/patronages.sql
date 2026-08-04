-- name: CountPatronages :one
select count(*)
from patronages
where lower(name) like $1 or lower(coalesce(description, '')) like $1;

-- name: ListPatronages :many
select p.id, p.name, p.slug, p.category, p.description, count(ps.saint_id)::int as saint_count
from patronages p
left join patronage_saint ps on ps.patronage_id = p.id
where lower(p.name) like $1 or lower(coalesce(p.description, '')) like $1
group by p.id
order by p.name
limit $2 offset $3;
