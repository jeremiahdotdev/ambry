select ro.id::text, ro.name, ro.slug, ro.abbreviation, ro.description, count(ros.saint_id)::int
from religious_orders ro
left join religious_order_saint ros on ros.religious_order_id = ro.id
where $1::text = ''
   or lower(ro.name) like $1
   or lower(coalesce(ro.description, '')) like $1
   or lower(coalesce(ro.abbreviation, '')) like $1
group by ro.id
order by ro.name asc
limit $2 offset $3;
