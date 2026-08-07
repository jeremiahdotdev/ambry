update developer_api_keys
set
    last_used_at = now(),
    updated_at = now()
where id = $1
    and (
        last_used_at is null
        or last_used_at < now() - $2::interval
    );
