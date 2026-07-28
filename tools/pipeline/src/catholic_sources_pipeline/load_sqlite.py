from __future__ import annotations

import json
import sqlite3
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable


def load_db_ready_json(input_path: Path, database_path: Path, chunk_size: int = 500) -> dict[str, int]:
    payload = json.loads(input_path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict) or not isinstance(payload.get("tables"), dict):
        raise ValueError("Input JSON must contain a tables object")

    tables = payload["tables"]
    sources = _rows(tables, "sources")
    source_documents = _rows(tables, "source_documents")
    citations = _rows(tables, "citations")

    database_path.parent.mkdir(parents=True, exist_ok=True)
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")

    with sqlite3.connect(database_path) as connection:
        connection.execute("PRAGMA foreign_keys = ON")
        _upsert_sources(connection, sources, now)
        _upsert_source_documents(connection, source_documents, now, chunk_size)
        _upsert_citations(connection, citations, now, chunk_size)

    return {
        "sources": len(sources),
        "source_documents": len(source_documents),
        "citations": len(citations),
    }


def load_saints_json(
    input_path: Path,
    database_path: Path,
    chunk_size: int = 500,
    review_input_path: Path | None = None,
) -> dict[str, int]:
    payload = json.loads(input_path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict) or payload.get("table") != "holy_people":
        raise ValueError("Input JSON must be a split DB-ready holy people file")

    rows = payload.get("rows")

    if not isinstance(rows, list):
        raise ValueError("Input JSON must include a rows array")

    reviews = _review_rows(review_input_path) if review_input_path else {}
    saints = _merge_holy_people_rows(rows, reviews)

    database_path.parent.mkdir(parents=True, exist_ok=True)
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")

    with sqlite3.connect(database_path) as connection:
        connection.execute("PRAGMA foreign_keys = ON")
        _clear_holy_people_tables(connection)
        _upsert_saints(connection, saints, now, chunk_size)
        patronage_count = _replace_patronages(connection, saints, now)
        feast_day_count = _replace_feast_days(connection, saints, now)

    return {
        "saints": len(saints),
        "patronages": patronage_count,
        "feast_days": feast_day_count,
    }


def _review_rows(review_input_path: Path) -> dict[str, dict[str, Any]]:
    payload = json.loads(review_input_path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict) or payload.get("table") != "holy_people_ai_reviews":
        raise ValueError("Review JSON must be a holy_people_ai_reviews split file")

    rows = payload.get("rows")

    if not isinstance(rows, list):
        raise ValueError("Review JSON must include a rows array")

    return {
        row["slug"]: row
        for row in rows
        if isinstance(row, dict) and isinstance(row.get("slug"), str)
    }


def _merge_holy_people_rows(
    structured_rows: list[Any],
    reviews: dict[str, dict[str, Any]],
) -> list[dict[str, Any]]:
    merged: list[dict[str, Any]] = []

    for row in structured_rows:
        if not isinstance(row, dict) or not isinstance(row.get("slug"), str):
            continue

        review = reviews.get(row["slug"], {})
        reviewed_type = review.get("type") or row.get("type")

        if reviewed_type == "not_holy_person":
            continue

        if reviewed_type == "church_father":
            reviewed_type = "saint"

        if reviewed_type not in {"saint", "blessed", "venerable", "pope", "holy_person"}:
            continue

        merged_row = {**row}
        merged_row["canonical_status"] = reviewed_type
        merged_row["type"] = reviewed_type

        for key in [
            "confidence",
            "reason",
            "virtues",
            "vices",
            "patronages",
            "feast_days",
            "roles",
            "image_prompt",
        ]:
            if key in review:
                merged_row[key] = review[key]

        if review.get("life_dates"):
            merged_row["life_dates"] = review["life_dates"]

        merged.append(merged_row)

    return merged


def _rows(tables: dict[str, Any], key: str) -> list[dict[str, Any]]:
    rows = tables.get(key)

    if not isinstance(rows, list):
        raise ValueError(f"Input JSON tables.{key} must be an array")

    return [row for row in rows if isinstance(row, dict)]


def _upsert_sources(connection: sqlite3.Connection, rows: list[dict[str, Any]], now: str) -> None:
    connection.executemany(
        """
        insert into sources (
            id, name, slug, type, license, attribution, canonical_url,
            reliability_notes, created_at, updated_at
        ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        on conflict(id) do update set
            name = excluded.name,
            slug = excluded.slug,
            type = excluded.type,
            license = excluded.license,
            attribution = excluded.attribution,
            canonical_url = excluded.canonical_url,
            reliability_notes = excluded.reliability_notes,
            updated_at = excluded.updated_at
        """,
        [
            (
                row["id"],
                row["name"],
                row["slug"],
                row.get("type"),
                row.get("license"),
                row.get("attribution"),
                row.get("canonical_url"),
                row.get("reliability_notes"),
                now,
                now,
            )
            for row in rows
        ],
    )


def _upsert_source_documents(
    connection: sqlite3.Connection,
    rows: list[dict[str, Any]],
    now: str,
    chunk_size: int,
) -> None:
    for chunk in _chunks(rows, chunk_size):
        connection.executemany(
            """
            insert into source_documents (
                id, source_id, title, slug, author, edition, language, url,
                raw_text, checksum, metadata, created_at, updated_at
            ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            on conflict(id) do update set
                source_id = excluded.source_id,
                title = excluded.title,
                slug = excluded.slug,
                author = excluded.author,
                edition = excluded.edition,
                language = excluded.language,
                url = excluded.url,
                raw_text = excluded.raw_text,
                checksum = excluded.checksum,
                metadata = excluded.metadata,
                updated_at = excluded.updated_at
            """,
            [
                (
                    row["id"],
                    row["source_id"],
                    row["title"],
                    row.get("slug"),
                    row.get("author"),
                    row.get("edition"),
                    row.get("language", "en"),
                    row.get("url"),
                    row.get("raw_text"),
                    row.get("checksum"),
                    json.dumps(row.get("metadata") or {}, ensure_ascii=False),
                    now,
                    now,
                )
                for row in chunk
            ],
        )


def _upsert_citations(
    connection: sqlite3.Connection,
    rows: list[dict[str, Any]],
    now: str,
    chunk_size: int,
) -> None:
    for chunk in _chunks(rows, chunk_size):
        connection.executemany(
            """
            insert into citations (
                id, source_id, title, locator, url, excerpt, accessed_at,
                created_at, updated_at
            ) values (?, ?, ?, ?, ?, ?, ?, ?, ?)
            on conflict(id) do update set
                source_id = excluded.source_id,
                title = excluded.title,
                locator = excluded.locator,
                url = excluded.url,
                excerpt = excluded.excerpt,
                accessed_at = excluded.accessed_at,
                updated_at = excluded.updated_at
            """,
            [
                (
                    row["id"],
                    row.get("source_id"),
                    row.get("title"),
                    row.get("locator"),
                    row.get("url"),
                    row.get("excerpt"),
                    row.get("accessed_at"),
                    now,
                    now,
                )
                for row in chunk
            ],
        )


def _upsert_saints(
    connection: sqlite3.Connection,
    rows: list[dict[str, Any]],
    now: str,
    chunk_size: int,
) -> None:
    for chunk in _chunks(rows, chunk_size):
        connection.executemany(
            """
            insert into saints (
                id, primary_name, slug, biography, birth_year, birth_year_qualifier,
                death_year, death_year_qualifier, life_dates, gender, canonical_status,
                is_martyr, is_doctor, virtues, vices, roles, ai_reason, ai_confidence,
                image_prompt, created_at, updated_at
            ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            on conflict(id) do update set
                primary_name = excluded.primary_name,
                slug = excluded.slug,
                biography = excluded.biography,
                birth_year = excluded.birth_year,
                birth_year_qualifier = excluded.birth_year_qualifier,
                death_year = excluded.death_year,
                death_year_qualifier = excluded.death_year_qualifier,
                life_dates = excluded.life_dates,
                gender = excluded.gender,
                canonical_status = excluded.canonical_status,
                is_martyr = excluded.is_martyr,
                is_doctor = excluded.is_doctor,
                virtues = excluded.virtues,
                vices = excluded.vices,
                roles = excluded.roles,
                ai_reason = excluded.ai_reason,
                ai_confidence = excluded.ai_confidence,
                image_prompt = excluded.image_prompt,
                updated_at = excluded.updated_at
            """,
            [
                (
                    row["id"],
                    row["primary_name"],
                    row["slug"],
                    row.get("biography"),
                    row.get("birth_year"),
                    row.get("birth_year_qualifier"),
                    row.get("death_year"),
                    row.get("death_year_qualifier"),
                    row.get("life_dates"),
                    row.get("gender"),
                    row.get("canonical_status", "saint"),
                    int(bool(row.get("is_martyr"))),
                    int(bool(row.get("is_doctor"))),
                    json.dumps(row.get("virtues") or [], ensure_ascii=False),
                    json.dumps(row.get("vices") or [], ensure_ascii=False),
                    json.dumps(row.get("roles") or [], ensure_ascii=False),
                    row.get("reason"),
                    row.get("confidence"),
                    row.get("image_prompt"),
                    now,
                    now,
                )
                for row in chunk
            ],
        )


def _clear_holy_people_tables(connection: sqlite3.Connection) -> None:
    for table in [
        "patronage_saint",
        "feast_days",
        "saint_aliases",
        "saints",
        "patronages",
    ]:
        connection.execute(f"delete from {table}")


def _replace_patronages(connection: sqlite3.Connection, rows: list[dict[str, Any]], now: str) -> int:
    patronages: dict[str, str] = {}
    pivots: list[tuple[str, str, str, str]] = []

    for row in rows:
        for name in row.get("patronages") or []:
            if not isinstance(name, str) or not name.strip():
                continue

            cleaned_name = " ".join(name.strip().split())
            slug = _slugify(cleaned_name)
            patronage_id = str(uuid.uuid5(uuid.NAMESPACE_URL, f"patronage:{slug}"))
            patronages[patronage_id] = cleaned_name
            pivots.append((row["id"], patronage_id, now, now))

    connection.executemany(
        """
        insert into patronages (id, name, slug, category, description, created_at, updated_at)
        values (?, ?, ?, ?, ?, ?, ?)
        on conflict(id) do update set
            name = excluded.name,
            slug = excluded.slug,
            updated_at = excluded.updated_at
        """,
        [
            (patronage_id, name, _slugify(name), None, None, now, now)
            for patronage_id, name in patronages.items()
        ],
    )
    connection.executemany(
        """
        insert or ignore into patronage_saint (
            saint_id, patronage_id, citation_id, confidence, is_tradition, created_at, updated_at
        ) values (?, ?, ?, ?, ?, ?, ?)
        """,
        [
            (saint_id, patronage_id, None, 0.9, 0, created_at, updated_at)
            for saint_id, patronage_id, created_at, updated_at in pivots
        ],
    )

    return len(pivots)


def _replace_feast_days(connection: sqlite3.Connection, rows: list[dict[str, Any]], now: str) -> int:
    feast_days: list[tuple[str, int, int, str, str, str]] = []

    for row in rows:
        for feast_day in row.get("feast_days") or []:
            if not isinstance(feast_day, dict):
                continue

            month = feast_day.get("month")
            day = feast_day.get("day")

            if not isinstance(month, int) or not isinstance(day, int):
                continue

            if month < 1 or month > 12 or day < 1 or day > 31:
                continue

            feast_days.append((
                row["id"],
                month,
                day,
                feast_day.get("calendar") or "general",
                now,
                now,
            ))

    connection.executemany(
        """
        insert into feast_days (
            saint_id, month, day, calendar, rite, locality, citation_id,
            confidence, created_at, updated_at
        ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        [
            (saint_id, month, day, calendar, None, None, None, 0.9, created_at, updated_at)
            for saint_id, month, day, calendar, created_at, updated_at in feast_days
        ],
    )

    return len(feast_days)


def _slugify(value: str) -> str:
    slug = []
    previous_dash = False

    for character in value.lower():
        if character.isalnum():
            slug.append(character)
            previous_dash = False
        elif not previous_dash:
            slug.append("-")
            previous_dash = True

    return "".join(slug).strip("-")


def _chunks(rows: list[dict[str, Any]], chunk_size: int) -> Iterable[list[dict[str, Any]]]:
    for index in range(0, len(rows), chunk_size):
        yield rows[index : index + chunk_size]
