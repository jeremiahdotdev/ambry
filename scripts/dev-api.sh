#!/usr/bin/env bash
set -euo pipefail

optional=false
if [[ "${1:-}" == "--optional" ]]; then
    optional=true
fi

skip_or_exit() {
    echo "$1"
    if [[ "$optional" == true ]]; then
        sleep 86400
    fi
    exit 1
}

if ! command -v go >/dev/null 2>&1; then
    skip_or_exit "Go is not installed; skipping API docs server. Install Go 1.24+ and rerun pnpm dev:api."
fi

load_env_file() {
    local file="$1"

    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ -z "$line" || "$line" == \#* || "$line" != *=* ]] && continue

        local key="${line%%=*}"
        local value="${line#*=}"

        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:${#value}-2}"
        elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
            value="${value:1:${#value}-2}"
        fi

        export "$key=$value"
    done < "$file"
}

cd apps/api

if [[ -f .env ]]; then
    load_env_file .env
fi

if [[ -z "${DATABASE_URL:-}" ]]; then
    skip_or_exit "DATABASE_URL is not set. Copy apps/api/.env.example to apps/api/.env and update DATABASE_URL."
fi

go run ./cmd/api
