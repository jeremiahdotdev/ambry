from __future__ import annotations

import json
import os
from dataclasses import dataclass
from pathlib import Path
from typing import Any


REVIEW_FIELDS_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "id": {"type": "string"},
        "slug": {"type": "string"},
        "primary_name": {"type": "string"},
        "type": {
            "type": "string",
            "enum": [
                "saint",
                "blessed",
                "venerable",
                "pope",
                "church_father",
                "holy_person",
                "not_holy_person",
            ],
        },
        "confidence": {"type": "number", "minimum": 0, "maximum": 1},
        "reason": {"type": "string"},
        "virtues": {
            "type": "array",
            "description": (
                "Concise lowercase keywords for holy qualities the saint exemplifies, such as humility, "
                "charity, fortitude, zeal, learning, poverty, obedience, perseverance, chastity, courage, or mercy."
            ),
            "items": {"type": "string"},
            "maxItems": 16,
        },
        "vices": {
            "type": "array",
            "description": (
                "Concise lowercase keywords for sins, temptations, disordered attachments, or moral/spiritual "
                "failings explicitly discussed as opposed, resisted, repented of, or overcome. Do not invent accusations."
            ),
            "items": {"type": "string"},
            "maxItems": 16,
        },
        "patronages": {
            "type": "array",
            "description": (
                "Concise lowercase keywords only for explicit 'patron saint of X' or 'patron of X' claims found in Catholic.org or other reliable Catholic sources. "
                "Cities, countries, regions, dioceses, and other places are valid patronages when explicitly stated. "
                "Return an empty array when no explicit patronage claim is found. Do not infer patronage from places, roles, offices, "
                "life events, miracles, founding activity, local veneration, or biography."
            ),
            "items": {"type": "string"},
            "maxItems": 24,
        },
        "feast_days": {
            "type": "array",
            "description": "Known liturgical or traditional feast days for the holy person.",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "month": {"type": ["integer", "null"], "minimum": 1, "maximum": 12},
                    "day": {"type": ["integer", "null"], "minimum": 1, "maximum": 31},
                    "display": {"type": "string"},
                    "calendar": {"type": ["string", "null"]},
                },
                "required": ["month", "day", "display", "calendar"],
            },
            "maxItems": 8,
        },
        "roles": {
            "type": "array",
            "description": (
                "Concise lowercase keywords for life states, offices, or recognized categories, such as bishop, martyr, "
                "monk, hermit, missionary, abbot, virgin, widow, pope, priest, nun, queen, or doctor of the church."
            ),
            "items": {"type": "string"},
            "maxItems": 16,
        },
        "life_dates": {
            "type": ["string", "null"],
            "description": (
                "Concise display string for lifespan when known, such as '387 - 493', 'c. 300 - 330', "
                "'4th century', or 'd. 604'. Use null if not enough reliable information is found."
            ),
        },
        "image_prompt": {
            "type": ["string", "null"],
            "description": (
                "A concise visual brief for later portrait generation. Include era, role, attire, symbols, and pose. "
                "Use null when type is not_holy_person."
            ),
        },
    },
    "required": [
        "id",
        "slug",
        "primary_name",
        "type",
        "confidence",
        "reason",
        "virtues",
        "vices",
        "patronages",
        "feast_days",
        "roles",
        "life_dates",
        "image_prompt",
    ],
}

BATCH_REVIEW_SCHEMA = {
    "name": "saint_ai_review_batch",
    "schema": {
        "type": "object",
        "additionalProperties": False,
        "properties": {
            "reviews": {
                "type": "array",
                "items": REVIEW_FIELDS_SCHEMA,
            },
        },
        "required": ["reviews"],
    },
    "strict": True,
}


@dataclass(frozen=True)
class AiEnrichmentOptions:
    input_path: Path
    review_output_path: Path
    model: str
    limit: int | None
    offset: int
    batch_size: int
    dry_run: bool


def run_ai_enrichment(options: AiEnrichmentOptions) -> dict[str, int]:
    rows = _load_saint_rows(options.input_path)
    selected_rows = rows[options.offset :]

    if options.limit is not None:
        selected_rows = selected_rows[: options.limit]

    if options.dry_run:
        return {
            "selected": len(selected_rows),
            "reviewed": 0,
            "requests": 0,
        }

    client = _openai_client()
    reviews_by_slug = _load_existing_reviews(options.review_output_path)
    reviewed = 0
    requests = 0

    for batch in _chunks(_reviewable_rows(selected_rows), options.batch_size):
        reviews, request_count = _review_saint_rows(client, batch, options.model)
        requests += request_count

        for review in reviews:
            slug = str(review.get("slug") or "").strip()

            if slug == "":
                continue

            reviews_by_slug[slug] = review
            reviewed += 1

        _write_reviews(options.review_output_path, reviews_by_slug)

    return {
        "selected": len(selected_rows),
        "reviewed": reviewed,
        "requests": requests,
    }


def _load_saint_rows(input_path: Path) -> list[dict[str, Any]]:
    payload = json.loads(input_path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict) or payload.get("table") != "holy_people":
        raise ValueError("Input JSON must be a split DB-ready holy people file")

    rows = payload.get("rows")

    if not isinstance(rows, list):
        raise ValueError("Input JSON must include a rows array")

    return [row for row in rows if isinstance(row, dict)]


def _openai_client() -> Any:
    _load_local_env_files()

    if not os.environ.get("OPENAI_API_KEY"):
        raise RuntimeError("OPENAI_API_KEY is required for ai-enrich")

    try:
        from openai import OpenAI
    except ImportError as exc:
        raise RuntimeError("Install pipeline dependencies before running ai-enrich") from exc

    return OpenAI()


def _load_local_env_files() -> None:
    for path in _candidate_env_paths():
        if path.is_file():
            _load_env_file(path)


def _candidate_env_paths() -> list[Path]:
    current_file = Path(__file__).resolve()
    pipeline_root = current_file.parents[2]
    repo_root = current_file.parents[4]

    return [
        pipeline_root / ".env",
        repo_root / ".env",
    ]


def _load_env_file(path: Path) -> None:
    for raw_line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw_line.strip()

        if line == "" or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()

        if key == "" or key in os.environ:
            continue

        os.environ[key] = _clean_env_value(value)


def _clean_env_value(value: str) -> str:
    value = value.strip()

    if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
        return value[1:-1]

    return value


def _load_existing_reviews(path: Path) -> dict[str, dict[str, Any]]:
    if not path.exists():
        return {}

    payload = json.loads(path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict):
        return {}

    rows = payload.get("rows", [])

    if not isinstance(rows, list):
        return {}

    return {
        str(row.get("slug")): row
        for row in rows
        if isinstance(row, dict) and row.get("slug")
    }


def _write_reviews(path: Path, reviews_by_slug: dict[str, dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    rows = sorted(reviews_by_slug.values(), key=lambda row: str(row.get("primary_name") or ""))
    path.write_text(
        json.dumps(
            {
                "table": "holy_people_ai_reviews",
                "count": len(rows),
                "rows": rows,
            },
            indent=2,
            ensure_ascii=False,
        )
        + "\n",
        encoding="utf-8",
    )


def _review_saint_rows(client: Any, rows: list[dict[str, Any]], model: str) -> tuple[list[dict[str, Any]], int]:
    reviews = _review_saint_batch(client, rows, model)
    expected_slugs = {str(row.get("slug") or "").strip() for row in rows}
    returned_by_slug = {
        str(review.get("slug") or "").strip(): review
        for review in reviews
        if str(review.get("slug") or "").strip() in expected_slugs
    }

    if set(returned_by_slug) == expected_slugs:
        return [returned_by_slug[str(row.get("slug") or "").strip()] for row in rows], 1

    if len(rows) == 1:
        missing = next(iter(expected_slugs - set(returned_by_slug)), next(iter(expected_slugs)))
        raise ValueError(f"AI review response omitted required candidate: {missing}")

    midpoint = max(1, len(rows) // 2)
    left_reviews, left_requests = _review_saint_rows(client, rows[:midpoint], model)
    right_reviews, right_requests = _review_saint_rows(client, rows[midpoint:], model)

    return left_reviews + right_reviews, 1 + left_requests + right_requests


def _review_saint_batch(client: Any, rows: list[dict[str, Any]], model: str) -> list[dict[str, Any]]:
    response = client.responses.create(
        model=model,
        input=[
            {
                "role": "system",
                "content": (
                    "You review Catholic Encyclopedia-derived candidate saint records. "
                    "Use web search when needed to validate identity, patronages, feast-related ambiguity, "
                    "or whether the candidate is actually a holy person. Prefer Catholic reference sources when available. "
                    "For patronages, search Catholic.org first and use its patronage wording when available. "
                    "If the entry is a mission, feast, place, organization, concept, title page, or other non-person "
                    "subject, return type=not_holy_person and leave virtues, vices, patronages, roles, life_dates, and image_prompt empty/null. "
                    "Affirm or correct type as saint, blessed, venerable, pope, church_father, holy_person, or not_holy_person. "
                    "Use church_father for recognized Fathers of the Church who are not better represented by saint or pope. "
                    "Use pope for popes where canonization status is not otherwise clear. "
                    "If the candidate is a holy person, return concise lowercase keyword arrays. Virtues must be simple qualities like "
                    "humility, charity, fortitude, zeal, learning, poverty, obedience, perseverance, chastity, courage, "
                    "or mercy. Do not put jobs, offices, achievements, places, or biographical events in virtues. "
                    "Roles are life states or offices like bishop, martyr, monk, hermit, missionary, abbot, virgin, "
                    "widow, pope, priest, nun, queen, or doctor of the church. Patronages must be empty unless Catholic.org or another reliable Catholic source "
                    "explicitly says 'patron saint of X' or 'patron of X'. Cities, countries, regions, dioceses, and other places are valid when explicit. "
                    "Do not infer patronage from being associated with a place, monastery, profession, miracle, feast, role, office, local devotion, scholarship, founding activity, or missionary work. "
                    "Feast days should include known "
                    "liturgical or traditional commemoration dates when reliable sources agree. Vices are sins, temptations, disordered attachments, or moral/spiritual "
                    "failings explicitly discussed as opposed, resisted, repented of, or overcome; do not invent accusations."
                    "If structured life_dates is blank or uncertain, provide a concise display string when reliable sources agree."
                    "Create image_prompt as a later-use portrait brief only; do not generate an image."
                    "Return exactly one review for every supplied candidate, preserving each candidate id and slug."
                ),
            },
            {
                "role": "user",
                "content": json.dumps(
                    {
                        "candidates": [_review_payload(row) for row in rows],
                    },
                    ensure_ascii=False,
                ),
            },
        ],
        tools=[{"type": "web_search"}],
        text={
            "format": {
                "type": "json_schema",
                **BATCH_REVIEW_SCHEMA,
            }
        },
    )
    parsed = json.loads(response.output_text)
    reviews = parsed.get("reviews", [])

    if not isinstance(reviews, list):
        raise ValueError("AI review response did not include a reviews array")

    normalized: list[dict[str, Any]] = []

    for review in reviews:
        if not isinstance(review, dict):
            continue

        review_type = str(review["type"])
        is_holy_person = review_type != "not_holy_person"
        normalized.append(
            {
                "id": review.get("id"),
                "slug": review.get("slug"),
                "primary_name": review.get("primary_name"),
                "type": review_type,
                "confidence": review["confidence"],
                "reason": review["reason"],
                "virtues": _clean_keywords(review["virtues"]) if is_holy_person else [],
                "vices": _clean_keywords(review["vices"]) if is_holy_person else [],
                "patronages": _clean_keywords(review["patronages"]) if is_holy_person else [],
                "feast_days": _clean_feast_days(review["feast_days"]) if is_holy_person else [],
                "roles": _clean_keywords(review["roles"]) if is_holy_person else [],
                "life_dates": review["life_dates"] if is_holy_person else None,
                "image_prompt": review["image_prompt"] if is_holy_person else None,
            }
        )

    return normalized


def _review_payload(row: dict[str, Any]) -> dict[str, Any]:
    metadata = row.get("metadata") if isinstance(row.get("metadata"), dict) else {}

    return {
        "primary_name": row.get("primary_name"),
        "slug": row.get("slug"),
        "canonical_status": row.get("canonical_status"),
        "birth_year": row.get("birth_year"),
        "birth_year_qualifier": row.get("birth_year_qualifier"),
        "death_year": row.get("death_year"),
        "death_year_qualifier": row.get("death_year_qualifier"),
        "life_dates": row.get("life_dates"),
        "gender": row.get("gender"),
        "source_title": metadata.get("source_title"),
        "source_url": metadata.get("url"),
        "source_hint": _source_hint(row.get("biography")),
    }


def _source_hint(biography: Any) -> str:
    text = str(biography or "").strip()

    if text == "":
        return ""

    return text[:700]


def _reviewable_rows(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [
        row
        for row in rows
        if str(row.get("slug") or "").strip()
    ]


def _chunks(rows: list[dict[str, Any]], chunk_size: int) -> list[list[dict[str, Any]]]:
    if chunk_size < 1:
        raise ValueError("batch_size must be at least 1")

    return [
        rows[index : index + chunk_size]
        for index in range(0, len(rows), chunk_size)
    ]


def _clean_keywords(values: list[Any]) -> list[str]:
    seen: set[str] = set()
    keywords: list[str] = []

    for value in values:
        keyword = str(value).strip().lower()

        if keyword == "" or keyword in seen:
            continue

        seen.add(keyword)
        keywords.append(keyword)

    return keywords


def _clean_feast_days(values: list[Any]) -> list[dict[str, Any]]:
    feast_days: list[dict[str, Any]] = []
    seen: set[tuple[int | None, int | None, str]] = set()

    for value in values:
        if not isinstance(value, dict):
            continue

        display = str(value.get("display") or "").strip()

        if display == "":
            continue

        month = _clean_int(value.get("month"))
        day = _clean_int(value.get("day"))
        calendar = str(value.get("calendar") or "").strip() or None
        key = (month, day, display.lower())

        if key in seen:
            continue

        seen.add(key)
        feast_days.append(
            {
                "month": month,
                "day": day,
                "display": display,
                "calendar": calendar,
            }
        )

    return feast_days


def _clean_int(value: Any) -> int | None:
    if value is None:
        return None

    try:
        return int(value)
    except (TypeError, ValueError):
        return None
