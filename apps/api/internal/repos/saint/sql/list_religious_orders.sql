select ro.name
from religious_orders ro
join religious_order_saint ros on ros.religious_order_id = ro.id
where ros.saint_id = $1
order by ro.name;
