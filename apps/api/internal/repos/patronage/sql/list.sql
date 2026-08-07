select p.id::text, p.name, p.slug, p.category, p.description, count(ps.saint_id)::int
from patronages p
left join patronage_saint ps on ps.patronage_id = p.id
where $1::text = ''
   or lower(p.name) like $1
   or lower(coalesce(p.description, '')) like $1
group by p.id
order by p.name asc
limit $2 offset $3;
