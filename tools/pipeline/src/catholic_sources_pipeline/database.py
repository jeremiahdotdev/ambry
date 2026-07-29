from __future__ import annotations

import os
import sqlite3
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator


DatabaseTarget = str | Path | None


def load_dotenv(path: Path = Path(".env")) -> None:
    if not path.is_file():
        return

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip("'\"")

        if key:
            os.environ.setdefault(key, value)


def database_label(target: DatabaseTarget = None) -> str:
    if _is_sqlite_target(target):
        return str(target)

    url = _postgres_url()
    if not url:
        return "app database"

    host = url.split("@", 1)[-1].split("/", 1)[0]

    return f"postgres:{host}"


def query_rows(target: DatabaseTarget, sql: str, params: list[Any] | tuple[Any, ...] = ()) -> list[dict[str, Any]]:
    if _is_sqlite_target(target):
        path = Path(target)

        if not path.exists():
            raise FileNotFoundError(f"SQLite database not found: {path}")

        with sqlite3.connect(path) as connection:
            connection.row_factory = sqlite3.Row
            rows = connection.execute(sql, params).fetchall()

        return [dict(row) for row in rows]

    with _postgres_connection() as connection:
        with connection.cursor() as cursor:
            cursor.execute(_postgres_placeholders(sql), tuple(params))
            columns = [column.name for column in cursor.description or []]

            return [dict(zip(columns, row, strict=True)) for row in cursor.fetchall()]


def execute(target: DatabaseTarget, sql: str, params: list[Any] | tuple[Any, ...] = ()) -> int:
    if _is_sqlite_target(target):
        path = Path(target)

        if not path.exists():
            raise FileNotFoundError(f"SQLite database not found: {path}")

        with sqlite3.connect(path) as connection:
            cursor = connection.execute(sql, params)
            connection.commit()

            return cursor.rowcount

    with _postgres_connection() as connection:
        with connection.cursor() as cursor:
            cursor.execute(_postgres_placeholders(sql), tuple(params))

            return cursor.rowcount


def json_param(target: DatabaseTarget, value: Any) -> Any:
    if _is_sqlite_target(target):
        import json

        return json.dumps(value, ensure_ascii=False)

    try:
        from psycopg.types.json import Json
    except ImportError as exc:
        raise RuntimeError(
            "psycopg is required for Postgres JSON pipeline writes. "
            "Run `pip install -e tools/pipeline`."
        ) from exc

    return Json(value)


def ensure_sqlite_columns(target: DatabaseTarget, columns: dict[str, str], *, dry_run: bool) -> None:
    if not _is_sqlite_target(target):
        return

    path = Path(target)
    connection = sqlite3.connect(path)
    existing = {row[1] for row in connection.execute("pragma table_info(saints)")}

    for column in columns.values():
        if column in existing:
            continue
        if dry_run:
            from .logging import log

            log(f"Would add saints.{column}")
            continue
        connection.execute(f"alter table saints add column {column} varchar")

    if not dry_run:
        connection.commit()

    connection.close()


def _is_sqlite_target(target: DatabaseTarget) -> bool:
    if target is None:
        return False

    value = str(target)

    return value.endswith(".sqlite") or value.startswith("sqlite:")


def _postgres_url() -> str | None:
    load_dotenv()

    return (
        os.environ.get("DB_URL")
        or os.environ.get("DATABASE_URL")
        or os.environ.get("POSTGRES_URL")
    )


@contextmanager
def _postgres_connection() -> Iterator[Any]:
    url = _postgres_url()

    if not url:
        raise RuntimeError("Postgres is not configured. Set DB_URL, DATABASE_URL, or POSTGRES_URL.")

    try:
        import psycopg
    except ImportError as exc:
        raise RuntimeError(
            "psycopg is required for Postgres pipeline access. "
            "Run `pip install -e tools/pipeline`."
        ) from exc

    with psycopg.connect(url, prepare_threshold=None) as connection:
        yield connection


def _postgres_placeholders(sql: str) -> str:
    return sql.replace("?", "%s")
