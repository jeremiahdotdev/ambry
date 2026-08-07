select
    id::text,
    primary_name,
    slug,
    canonical_status,
    biography,
    birth_year,
    death_year,
    coalesce(profile_landmarks, '[]'::json)::jsonb,
    coalesce(profile_church_roles, roles, '[]'::json)::jsonb
from saints
where slug = $1;
