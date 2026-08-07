select count(distinct s.id)
from saints s
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
);
