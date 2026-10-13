select
    id::text,
    primary_name,
    slug,
    canonical_status,
    biography,
    birth_year,
    death_year,
    image_portrait_url,
    image_avatar_url,
    coalesce(profile_landmarks, '[]'::json)::jsonb,
    coalesce(profile_church_roles, roles, '[]'::json)::jsonb
from saints
where slug = $1;
