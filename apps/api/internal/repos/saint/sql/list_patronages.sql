select p.name
from patronages p
join patronage_saint ps on ps.patronage_id = p.id
where ps.saint_id = $1
order by p.name;
