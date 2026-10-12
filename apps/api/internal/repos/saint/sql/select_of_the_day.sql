-- name: SelectSaintOfTheDay :one
with selected as (
    insert into saint_of_the_day (selection_date, saint_id)
    select $1::date, s.id
    from saints s
    where s.canonical_status = 'saint'
      and not exists (
          select 1
          from saint_of_the_day used
          where used.selection_year = extract(year from $1::date)::integer
            and used.saint_id = s.id
      )
    order by exists (
        select 1 from feast_days f
        where f.saint_id = s.id
          and f.month = extract(month from $1::date)::integer
          and f.day = extract(day from $1::date)::integer
    ) desc, random()
    limit 1
    returning saint_id
)
select s.slug
from selected
join saints s on s.id = selected.saint_id;
