select alias
from saint_aliases
where saint_id = $1
order by alias;
