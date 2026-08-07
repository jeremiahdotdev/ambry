-- name: ListFeastDaySaintIDs :many
select distinct saint_id
from feast_days
where ($1::smallint is null or month = $1)
  and ($2::smallint is null or day = $2)
order by saint_id
limit $3 offset $4;
