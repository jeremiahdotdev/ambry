select count(*)
from religious_orders ro
where $1::text = ''
   or lower(ro.name) like $1
   or lower(coalesce(ro.description, '')) like $1
   or lower(coalesce(ro.abbreviation, '')) like $1;
