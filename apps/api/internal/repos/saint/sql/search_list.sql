select
    s.id::text,
    s.primary_name,
    s.slug,
    s.canonical_status,
    s.birth_year,
    s.death_year,
    coalesce(nullif(s.profile_summary, ''), left(coalesce(s.biography, ''), 250)) as summary,
    coalesce(jsonb_agg(distinct jsonb_build_object('month', fd.month, 'day', fd.day, 'name', fd.calendar)) filter (where fd.id is not null), '[]'::jsonb) as feast_days,
    coalesce(array_remove(array_agg(distinct p.name), null::varchar), '{}') as patronages,
    coalesce(array_remove(array_agg(distinct ro.name), null::varchar), '{}') as religious_orders
from saints s
left join feast_days fd on fd.saint_id = s.id
left join patronage_saint ps on ps.saint_id = s.id
left join patronages p on p.id = ps.patronage_id
left join religious_order_saint ros on ros.saint_id = s.id
left join religious_orders ro on ro.id = ros.religious_order_id
where (
    $1::text = ''
    or lower(s.primary_name) like $1
    or lower(coalesce(s.biography, '')) like $1
    or exists (
        select 1
        from saint_aliases sa
        where sa.saint_id = s.id and lower(sa.alias) like $1
    )
    or exists (
        select 1
        from patronage_saint ps2
        join patronages p2 on p2.id = ps2.patronage_id
        where ps2.saint_id = s.id and lower(p2.name) like $1
    )
    or exists (
        select 1
        from religious_order_saint ros2
        join religious_orders ro2 on ro2.id = ros2.religious_order_id
        where ros2.saint_id = s.id and lower(ro2.name) like $1
    )
)
and ($2::text = '' or s.canonical_status = $2)
and (
    $3::text = ''
    or exists (
        select 1
        from patronage_saint ps3
        join patronages p3 on p3.id = ps3.patronage_id
        where ps3.saint_id = s.id and p3.slug = $3
    )
)
and (
    $4::text = ''
    or exists (
        select 1
        from religious_order_saint ros3
        join religious_orders ro3 on ro3.id = ros3.religious_order_id
        where ros3.saint_id = s.id and ro3.slug = $4
    )
)
and (
    $5::int = 0
    or exists (
        select 1
        from feast_days fd3
        where fd3.saint_id = s.id and fd3.month = $5
    )
)
and (
    $6::int = 0
    or exists (
        select 1
        from feast_days fd4
        where fd4.saint_id = s.id and fd4.day = $6
    )
)
group by s.id
order by
    case when $9::text = 'birth_year' and $10::text = 'asc' then s.birth_year end asc nulls last,
    case when $9::text = 'birth_year' and $10::text = 'desc' then s.birth_year end desc nulls last,
    case when $9::text = 'death_year' and $10::text = 'asc' then s.death_year end asc nulls last,
    case when $9::text = 'death_year' and $10::text = 'desc' then s.death_year end desc nulls last,
    case when $9::text = 'feast_day' and $10::text = 'asc' then min(fd.month * 100 + fd.day) end asc nulls last,
    case when $9::text = 'feast_day' and $10::text = 'desc' then min(fd.month * 100 + fd.day) end desc nulls last,
    case when $9::text = 'name' and $10::text = 'asc' then s.primary_name end asc,
    case when $9::text = 'name' and $10::text = 'desc' then s.primary_name end desc,
    s.primary_name asc
limit $7 offset $8;
