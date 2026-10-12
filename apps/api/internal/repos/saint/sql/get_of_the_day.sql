-- name: GetSaintOfTheDay :one
select s.slug
from saint_of_the_day d
join saints s on s.id = d.saint_id
where d.selection_date = $1::date;
