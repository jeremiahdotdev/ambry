#!/usr/bin/env python3
"""Persist the search-list crop in saints.image_avatar_url; never edit originals."""

import argparse
import hashlib
import io
import os
from pathlib import Path
import re
import sqlite3
import sys
import warnings
from urllib.parse import urlsplit

from dotenv import load_dotenv
from PIL import Image, ImageOps
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


# resources/css/search/results.css: full thumbnail height 20rem, visible 11rem.
# The mobile list clips this same region further to 9.4rem.
VISIBLE_HEIGHT = 11 / 20
MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024
Image.MAX_IMAGE_PIXELS = 25_000_000
warnings.simplefilter("error", Image.DecompressionBombWarning)


def crop_avatar(source: bytes) -> bytes:
    with Image.open(io.BytesIO(source)) as image:
        image = ImageOps.exif_transpose(image).convert("RGBA")
        # Preserve the entire width and aspect ratio of the visible CSS region.
        # Do not use a square cover crop: that would zoom or trim the saint again.
        height = max(1, round(image.height * VISIBLE_HEIGHT))
        avatar = image.crop((0, 0, image.width, height))
        avatar.thumbnail((512, 512), Image.Resampling.LANCZOS)
        output = io.BytesIO()
        avatar.save(output, "PNG", optimize=True)
        return output.getvalue()


def http_session() -> requests.Session:
    session = requests.Session()
    retries = Retry(total=3, backoff_factor=0.5, status_forcelist=[429, 500, 502, 503, 504],
                    allowed_methods=["GET", "PUT"], respect_retry_after_header=True)
    session.mount("https://", HTTPAdapter(max_retries=retries))
    session.mount("http://", HTTPAdapter(max_retries=retries))
    return session


def read_source(source: str | Path, session: requests.Session) -> bytes:
    if isinstance(source, Path):
        if source.stat().st_size > MAX_DOWNLOAD_BYTES:
            raise ValueError("source exceeds the 10 MB limit")
        return source.read_bytes()
    url = urlsplit(source)
    if url.scheme not in {"https", "http"} or not url.hostname or url.username or url.password:
        raise ValueError("source must be a public HTTP(S) image URL")
    with session.get(source, stream=True, timeout=(10, 30)) as response:
        if not response.ok:
            raise ValueError(f"image download returned HTTP {response.status_code}")
        chunks = []
        size = 0
        for chunk in response.iter_content(64 * 1024):
            size += len(chunk)
            if size > MAX_DOWNLOAD_BYTES:
                raise ValueError("source exceeds the 10 MB limit")
            chunks.append(chunk)
        return b"".join(chunks)


def search_image(row: dict, web_root: Path) -> str | Path:
    if row["image_thumb_url"]:
        return row["image_thumb_url"]
    # Mirror search/results.blade.php, including its local/default fallbacks.
    slug = row["slug"]
    if not re.fullmatch(r"[A-Za-z0-9_-]+", slug):
        raise ValueError("invalid saint slug")
    for path in (web_root / "storage/app/generated/saints" / slug / "thumb.webp",
                 web_root / "public/saints" / f"{slug}.png",
                 web_root / "public/saints/default.png"):
        if path.is_file():
            return path
    raise ValueError("no search-result image is available")


def upload_avatar(session: requests.Session, token: str, saint_id: str, avatar: bytes) -> str:
    # A separate, content-addressed avatar path protects the existing image blobs
    # and lets a retry reuse identical content without stale CDN cache issues.
    if not re.fullmatch(r"[A-Za-z0-9_-]+", saint_id):
        raise ValueError("invalid saint ID")
    digest = hashlib.sha256(avatar).hexdigest()
    pathname = f"saints/{saint_id}/avatars/{digest}.png"
    # Wire format follows vercel/storage packages/blob/src/{api,put,put-helpers}.ts.
    response = session.put(
        "https://vercel.com/api/blob/", params={"pathname": pathname}, data=avatar,
        headers={"Authorization": f"Bearer {token}", "x-api-version": "12",
                 "x-vercel-blob-access": "public", "x-content-type": "image/png",
                 "Content-Type": "image/png", "x-add-random-suffix": "0",
                 "x-allow-overwrite": "1", "x-content-length": str(len(avatar))},
        timeout=(10, 60), allow_redirects=False,
    )
    if not response.ok:
        raise ValueError(f"avatar upload returned HTTP {response.status_code}")
    url = response.json().get("url", "")
    parsed = urlsplit(url)
    if (parsed.scheme != "https" or not parsed.hostname
            or not parsed.hostname.endswith(".public.blob.vercel-storage.com")
            or parsed.username or parsed.password):
        raise ValueError("upload did not return a public Blob image URL")
    return url


def connect_database(database: str):
    if database.startswith(("postgres://", "postgresql://")):
        import psycopg
        from psycopg.rows import dict_row
        return psycopg.connect(database, autocommit=True, row_factory=dict_row, connect_timeout=10)
    path = Path(database).expanduser().resolve()
    if not path.is_file():
        raise ValueError("SQLite database does not exist")
    connection = sqlite3.connect(str(path), isolation_level=None)
    connection.row_factory = sqlite3.Row
    return connection


def backfill(connection, web_root: Path, session: requests.Session, *, apply=False,
             token="", slug=None, limit=None, force=False, preview_dir=None) -> dict:
    placeholder = "?" if isinstance(connection, sqlite3.Connection) else "%s"
    query = "SELECT id, slug, image_thumb_url, image_avatar_url FROM saints"
    conditions, params = [], []
    if not force:
        conditions.append("(image_avatar_url IS NULL OR image_avatar_url = '')")
    if slug:
        conditions.append(f"slug = {placeholder}")
        params.append(slug)
    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    query += " ORDER BY id"
    if limit:
        query += f" LIMIT {placeholder}"
        params.append(limit)
    # Materialize rows, then release the read cursor before network work. Each
    # update commits independently so rerunning skips already-completed saints.
    cursor = connection.execute(query, params)
    rows = [dict(row) for row in cursor.fetchall()]
    cursor.close()
    stats = {"selected": len(rows), "prepared": 0, "saved": 0, "conflicts": 0, "failed": 0}
    if apply and not token:
        raise ValueError("BLOB_READ_WRITE_TOKEN is required with --apply")
    for row in rows:
        try:
            avatar = crop_avatar(read_source(search_image(row, web_root), session))
            stats["prepared"] += 1
            if preview_dir:
                preview_dir.mkdir(parents=True, exist_ok=True)
                # search_image validates the slug before any filesystem writes.
                if not re.fullmatch(r"[A-Za-z0-9_-]+", row["slug"]):
                    raise ValueError("invalid saint slug")
                (preview_dir / f'{row["slug"]}.png').write_bytes(avatar)
            if apply:
                url = upload_avatar(session, token, str(row["id"]), avatar)
                # Compare the original thumbnail and avatar values to avoid
                # replacing a concurrent edit. Only image_avatar_url is written.
                operator = "IS" if isinstance(connection, sqlite3.Connection) else "IS NOT DISTINCT FROM"
                guard = lambda column: f"{column} {operator} {placeholder}"
                update = connection.execute(
                    f"UPDATE saints SET image_avatar_url = {placeholder} WHERE id = {placeholder} "
                    f"AND {guard('image_thumb_url')} AND {guard('image_avatar_url')}",
                    (url, row["id"], row["image_thumb_url"], row["image_avatar_url"]),
                )
                saved = update.rowcount == 1
                update.close()
                stats["saved" if saved else "conflicts"] += 1
                print(f'{row["slug"]}: {"saved" if saved else "skipped concurrent edit"}')
            else:
                print(f'{row["slug"]}: crop ready (dry run)')
        except Exception as error:
            stats["failed"] += 1
            # Requests/DB exception text can contain tokens or connection URLs.
            detail = str(error) if isinstance(error, ValueError) else type(error).__name__
            print(f'{row["slug"]}: failed: {detail}', file=sys.stderr)
    return stats


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    web_root = Path(__file__).resolve().parents[2]
    parser.add_argument("--env-file", type=Path, default=web_root / ".env")
    parser.add_argument("--database", help="PostgreSQL URL or existing SQLite path; defaults to DB_URL/DATABASE_URL")
    parser.add_argument("--apply", action="store_true", help="upload avatars and save image_avatar_url; default is dry run")
    parser.add_argument("--slug", help="process one saint")
    parser.add_argument("--limit", type=int, help="maximum records to process")
    parser.add_argument("--force", action="store_true", help="regenerate existing avatars")
    parser.add_argument("--preview-dir", type=Path, help="also save local PNG previews")
    args = parser.parse_args()
    if args.limit is not None and args.limit < 1:
        parser.error("--limit must be positive")
    load_dotenv(args.env_file, override=False)
    database = args.database or os.getenv("DB_URL") or os.getenv("DATABASE_URL")
    if not database:
        parser.error("set DB_URL/DATABASE_URL or pass --database")
    try:
        with connect_database(database) as connection, http_session() as session:
            stats = backfill(connection, web_root, session, apply=args.apply,
                             token=os.getenv("BLOB_READ_WRITE_TOKEN", ""), slug=args.slug,
                             limit=args.limit, force=args.force, preview_dir=args.preview_dir)
    except Exception as error:
        detail = str(error) if isinstance(error, ValueError) else type(error).__name__
        print(f"Backfill failed: {detail}. Ensure the avatar migration has been applied.", file=sys.stderr)
        return 1
    print(", ".join(f"{key}={value}" for key, value in stats.items()))
    return int(stats["failed"] > 0 or stats["conflicts"] > 0)


if __name__ == "__main__":
    raise SystemExit(main())
