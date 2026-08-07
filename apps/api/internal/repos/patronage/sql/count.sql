select count(*)
from patronages p
where $1::text = ''
   or lower(p.name) like $1
   or lower(coalesce(p.description, '')) like $1;
