select id, user_id
from developer_api_keys
where token_hash = $1
    and revoked_at is null
    and (expires_at is null or expires_at > now())
limit 1;
