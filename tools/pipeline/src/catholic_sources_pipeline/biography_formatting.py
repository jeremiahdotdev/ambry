from __future__ import annotations

import json
import os
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any

from .database import DatabaseTarget, database_label, execute, json_param, load_dotenv, query_rows
from .logging import log


SECTION_SCHEMA = {
    "name": "ambry_biography_sections",
    "strict": True,
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "properties": {
            "sections": {
                "type": "array",
                "minItems": 1,
                "maxItems": 24,
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": {
                        "heading": {
                            "type": "string",
                            "description": "A short section heading, title case, 2 to 6 words.",
                        },
                        "start_paragraph": {
                            "type": "integer",
                            "minimum": 1,
                        },
                        "end_paragraph": {
                            "type": "integer",
                            "minimum": 1,
                        },
                    },
                    "required": ["heading", "start_paragraph", "end_paragraph"],
                },
            },
        },
        "required": ["sections"],
    },
}


@dataclass(frozen=True)
class BiographyFormattingOptions:
    database_path: DatabaseTarget
    model: str = "gpt-4.1-mini"
    limit: int | None = 1
    offset: int = 0
    slug: str | None = None
    force: bool = False
    dry_run: bool = False
    max_input_chars: int | None = None
    canonical_status: str | None = None


def run_biography_formatting(options: BiographyFormattingOptions) -> dict[str, int]:
    load_dotenv()
    _ensure_api_key(options.dry_run)
    rows = _select_saints(options)

    log(
        "Biography formatting queue: "
        f"{len(rows)} selected, model={options.model}, db={database_label(options.database_path)}"
    )

    counts = {
        "selected": len(rows),
        "formatted": 0,
        "failed": 0,
    }

    if options.dry_run:
        for row in rows:
            log(f"{row['slug']}: {row['primary_name']} ({len(str(row.get('biography') or ''))} chars)")

        return counts

    from openai import OpenAI

    client = OpenAI()

    for index, row in enumerate(rows, start=1):
        slug = row["slug"]
        log(f"[{index}/{len(rows)}] Formatting biography for {slug}")
        sources = _source_catalog(options.database_path, row)

        try:
            sections = _format_biography(client, row, sources, options)
        except Exception as exc:
            counts["failed"] += 1
            _record_error(options.database_path, slug, str(exc))
            log(f"[{index}/{len(rows)}] Failed {slug}: {exc}")
            continue

        _update_saint_biography_sections(options.database_path, slug, sections, sources, options.model)
        counts["formatted"] += 1
        log(f"[{index}/{len(rows)}] Updated biography sections for {slug}")

    return counts


def _select_saints(options: BiographyFormattingOptions) -> list[dict[str, Any]]:
    filters = [
        "biography is not null",
        "trim(biography) != ''",
    ]
    params: list[Any] = []

    if options.slug:
        filters.append("slug = ?")
        params.append(options.slug)
    elif options.canonical_status:
        filters.append("lower(canonical_status) = lower(?)")
        params.append(options.canonical_status)
        if not options.force:
            filters.append("(biography_sections is null or biography_format_error is not null)")
    elif not options.force:
        filters.append("(biography_sections is null or biography_format_error is not null)")

    limit_sql = "" if options.limit is None or options.slug else "limit ? offset ?"

    if options.limit is not None and not options.slug:
        params.extend([options.limit, options.offset])

    return query_rows(
        options.database_path,
        f"""
        select id, primary_name, slug, biography, canonical_status, life_dates
        from saints
        where {' and '.join(filters)}
        order by slug
        {limit_sql}
        """,
        params,
    )


def _source_catalog(database_path: DatabaseTarget, row: dict[str, Any]) -> list[dict[str, Any]]:
    biography = str(row.get("biography") or "")
    documents = query_rows(
        database_path,
        """
        select title, slug, url
        from source_documents
        where raw_text = ?
        order by title
        limit 6
        """,
        [biography],
    )

    if not documents:
        names = _source_title_candidates(str(row["primary_name"]))
        documents = query_rows(
            database_path,
            """
            select title, slug, url
            from source_documents
            where lower(title) in (?, ?, ?, ?)
            order by title
            limit 6
            """,
            [name.lower() for name in names],
        )

    return [
        {
            "marker": f"source:{index}",
            "title": str(document.get("title") or document.get("slug") or f"Source {index}").strip(),
            "locator": document.get("slug"),
            "url": _clean_url(document.get("url")),
        }
        for index, document in enumerate(documents, start=1)
    ]


def _format_biography(
    client: Any,
    row: dict[str, Any],
    sources: list[dict[str, Any]],
    options: BiographyFormattingOptions,
) -> list[dict[str, Any]]:
    biography = str(row.get("biography") or "").strip()
    paragraphs = _biography_paragraphs(biography)
    source_marker = sources[0]["marker"] if len(sources) == 1 else None

    if options.max_input_chars is not None and len(biography) > options.max_input_chars:
        raise ValueError(
            f"Biography is {len(biography)} chars but max_input_chars is {options.max_input_chars}; "
            "increase the limit or omit it to process the full text."
        )

    response = client.responses.create(
        model=options.model,
        input=[
            {
                "role": "system",
                "content": (
                    "You format saint biography text for Ambry by adding section headings. "
                    "Do not summarize, paraphrase, modernize, embellish, or omit material. "
                    "Return paragraph ranges only; the application will preserve the original wording. "
                    "Choose meaningful semantic sections from the supplied numbered paragraphs. "
                    "Ranges must cover every paragraph exactly once, in order, without gaps or overlaps. "
                    "Return only valid JSON."
                ),
            },
            {
                "role": "user",
                "content": json.dumps(
                    {
                        "saint": {
                            "name": row.get("primary_name"),
                            "slug": row.get("slug"),
                            "canonical_status": row.get("canonical_status"),
                            "life_dates": row.get("life_dates"),
                        },
                        "sources": sources,
                        "paragraph_count": len(paragraphs),
                        "instructions": [
                            "Use every paragraph from 1 through paragraph_count exactly once.",
                            "Each section range must start at the next uncovered paragraph.",
                            "The final section must end at paragraph_count.",
                            "Create as many sections as needed for coherent reading, usually 4 to 14 for long texts.",
                            "Headings should be short title-case labels for the included paragraphs.",
                            "Do not create, remove, merge, split, or rewrite paragraphs.",
                            "Do not invent facts, dates, miracles, patronages, citations, URLs, or source markers.",
                        ],
                        "paragraphs": [
                            {"number": index, "text": paragraph}
                            for index, paragraph in enumerate(paragraphs, start=1)
                        ],
                    },
                    ensure_ascii=False,
                ),
            },
        ],
        text={
            "format": {
                "type": "json_schema",
                **SECTION_SCHEMA,
            }
        },
    )
    payload = json.loads(response.output_text)
    sections = payload.get("sections")

    if not isinstance(sections, list) or not sections:
        raise ValueError("OpenAI response did not include sections")

    return _sections_from_ranges(sections, paragraphs, source_marker)


def _sections_from_ranges(
    raw_sections: list[Any],
    paragraphs: list[str],
    source_marker: str | None,
) -> list[dict[str, Any]]:
    sections: list[dict[str, Any]] = []
    expected_start = 1

    for raw_section in raw_sections:
        if not isinstance(raw_section, dict):
            continue

        start = int(raw_section.get("start_paragraph") or 0)
        end = int(raw_section.get("end_paragraph") or 0)

        if start != expected_start:
            raise ValueError(f"Section ranges must cover paragraphs in order; expected {expected_start}, got {start}")

        if end < start or end > len(paragraphs):
            raise ValueError(f"Invalid section range: {start}-{end}")

        body = "\n\n".join(paragraphs[start - 1:end]).strip()
        markers = []

        if source_marker:
            body = f"{body}[{source_marker}]"
            markers = [source_marker]

        sections.append(
            {
                "heading": str(raw_section.get("heading") or "Life").strip()[:80],
                "body": body,
                "source_markers": markers,
            }
        )
        expected_start = end + 1

    if expected_start != len(paragraphs) + 1:
        raise ValueError(f"Section ranges omitted paragraphs {expected_start}-{len(paragraphs)}")

    return sections


def _biography_paragraphs(biography: str) -> list[str]:
    return [
        paragraph
        for paragraph in (part.strip() for part in biography.split("\n\n"))
        if paragraph
    ]


def _source_title_candidates(primary_name: str) -> list[str]:
    stripped = primary_name.removeprefix("Saint ").removeprefix("St. ")

    return [
        primary_name,
        f"{primary_name}.",
        stripped,
        f"{stripped}.",
    ]


def _update_saint_biography_sections(
    database_path: DatabaseTarget,
    slug: str,
    sections: list[dict[str, Any]],
    sources: list[dict[str, Any]],
    model: str,
) -> None:
    execute(
        database_path,
        """
        update saints
        set biography_sections = ?,
            biography_sources = ?,
            biography_format_model = ?,
            biography_formatted_at = current_timestamp,
            biography_format_error = null,
            updated_at = current_timestamp
        where slug = ?
        """,
        (
            json_param(database_path, sections),
            json_param(database_path, sources),
            model,
            slug,
        ),
    )


def _record_error(database_path: DatabaseTarget, slug: str, error: str) -> None:
    execute(
        database_path,
        """
        update saints
        set biography_format_error = ?,
            updated_at = current_timestamp
        where slug = ?
        """,
        (error[:2000], slug),
    )


def _clean_url(value: Any) -> str | None:
    if value is None:
        return None

    url = str(value).strip()

    if not url:
        return None

    return url.replace("http: //", "http://").replace("https: //", "https://")


def _ensure_api_key(dry_run: bool) -> None:
    if dry_run:
        return

    if not os.environ.get("OPENAI_API_KEY"):
        raise RuntimeError("OPENAI_API_KEY must be set to format biographies")
