from __future__ import annotations

import json
import mimetypes
import os
import sqlite3
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Any


ASSETS: dict[str, tuple[str, str]] = {
    "cutout": ("cutout.png", "image/png"),
    "portrait": ("portrait.webp", "image/webp"),
    "thumb": ("thumb.webp", "image/webp"),
}

URL_COLUMNS: dict[str, str] = {
    "cutout": "image_cutout_url",
    "portrait": "image_portrait_url",
    "thumb": "image_thumb_url",
}


@dataclass(frozen=True)
class BlobUploadOptions:
    database_path: Path
    input_dir: Path
    prefix: str = "saints/v1"
    slug: str | None = None
    limit: int | None = 1
    offset: int = 0
    node_script: Path = Path("tools/pipeline/bin/upload-vercel-blob.mjs")
    dry_run: bool = False


def run_blob_upload(options: BlobUploadOptions) -> dict[str, int]:
    rows = _select_saints(options)
    _ensure_columns(options.database_path, dry_run=options.dry_run)

    counts = {
        "selected": len(rows),
        "uploaded_files": 0,
        "updated_rows": 0,
        "missing_assets": 0,
    }

    for row in rows:
        slug = row["slug"]
        urls: dict[str, str] = {}

        for kind, (filename, content_type) in ASSETS.items():
            local_path = options.input_dir / slug / filename
            if not local_path.is_file():
                counts["missing_assets"] += 1
                print(f"Missing {local_path}")
                continue

            pathname = _blob_path(options.prefix, slug, filename)

            if options.dry_run:
                print(f"{slug}: {local_path} -> {pathname}")
                urls[kind] = f"https://example.vercel-storage.com/{pathname}"
            else:
                upload = _upload_file(options.node_script, local_path, pathname, content_type)
                urls[kind] = upload["url"]

            counts["uploaded_files"] += 1

        if set(urls) == set(ASSETS):
            _update_saint_urls(options.database_path, slug, urls, dry_run=options.dry_run)
            counts["updated_rows"] += 1
            print(f"Updated {slug}")

    return counts


def _select_saints(options: BlobUploadOptions) -> list[dict[str, Any]]:
    connection = sqlite3.connect(options.database_path)
    connection.row_factory = sqlite3.Row

    where = []
    parameters: list[Any] = []

    if options.slug:
        where.append("slug = ?")
        parameters.append(options.slug)
    else:
        slugs = _local_asset_slugs(options.input_dir)
        if not slugs:
            connection.close()
            return []
        placeholders = ", ".join("?" for _ in slugs)
        where.append(f"slug in ({placeholders})")
        parameters.extend(slugs)

    sql = "select id, slug from saints"
    if where:
        sql += " where " + " and ".join(where)
    sql += " order by slug"

    if options.limit is not None:
        sql += " limit ? offset ?"
        parameters.extend([options.limit, options.offset])

    rows = [dict(row) for row in connection.execute(sql, parameters)]
    connection.close()

    return rows


def _local_asset_slugs(input_dir: Path) -> list[str]:
    if not input_dir.is_dir():
        return []

    return sorted(
        path.name
        for path in input_dir.iterdir()
        if path.is_dir() and all((path / filename).is_file() for filename, _ in ASSETS.values())
    )


def _ensure_columns(database_path: Path, *, dry_run: bool) -> None:
    connection = sqlite3.connect(database_path)
    existing = {row[1] for row in connection.execute("pragma table_info(saints)")}

    for column in URL_COLUMNS.values():
        if column in existing:
            continue
        if dry_run:
            print(f"Would add saints.{column}")
            continue
        connection.execute(f"alter table saints add column {column} varchar")

    if not dry_run:
        connection.commit()

    connection.close()


def _upload_file(node_script: Path, local_path: Path, pathname: str, content_type: str) -> dict[str, Any]:
    resolved_content_type = content_type or mimetypes.guess_type(local_path.name)[0] or "application/octet-stream"
    env = os.environ.copy()

    result = subprocess.run(
        [
            "node",
            str(node_script),
            str(local_path),
            pathname,
            resolved_content_type,
        ],
        check=True,
        capture_output=True,
        env=env,
        text=True,
    )

    return json.loads(result.stdout)


def _update_saint_urls(database_path: Path, slug: str, urls: dict[str, str], *, dry_run: bool) -> None:
    if dry_run:
        return

    connection = sqlite3.connect(database_path)
    connection.execute(
        """
        update saints
        set image_cutout_url = ?,
            image_portrait_url = ?,
            image_thumb_url = ?,
            updated_at = current_timestamp
        where slug = ?
        """,
        (urls["cutout"], urls["portrait"], urls["thumb"], slug),
    )
    connection.commit()
    connection.close()


def _blob_path(prefix: str, slug: str, filename: str) -> str:
    return "/".join(part.strip("/") for part in [prefix, slug, filename] if part.strip("/"))
