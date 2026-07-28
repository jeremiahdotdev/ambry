from __future__ import annotations

import hashlib
import json
import re
import uuid
from pathlib import Path
from typing import Any

from .text import excerpt, normalize_text


SOURCE_ID = str(uuid.uuid5(uuid.NAMESPACE_URL, "new-advent:catholic-encyclopedia"))
MONTHS = {
    "january": 1,
    "february": 2,
    "march": 3,
    "april": 4,
    "may": 5,
    "june": 6,
    "july": 7,
    "august": 8,
    "september": 9,
    "october": 10,
    "november": 11,
    "december": 12,
}


def build_db_ready_payload(documents_payload: dict[str, Any]) -> dict[str, Any]:
    documents = documents_payload.get("documents")

    if not isinstance(documents, list):
        raise ValueError("Input JSON must include a documents array")

    source = {
        "id": SOURCE_ID,
        "name": "Catholic Encyclopedia",
        "slug": "catholic-encyclopedia",
        "type": "catholic_public_domain",
        "license": "Public domain in the United States; New Advent local copy requires use review",
        "attribution": "The Catholic Encyclopedia, Robert Appleton Company, 1907-1913; local New Advent HTML copy",
        "canonical_url": "https://www.newadvent.org/cathen/",
        "reliability_notes": "Converted from local New Advent HTML. This artifact prepares source documents and citations only.",
    }

    source_documents: list[dict[str, Any]] = []
    citations: list[dict[str, Any]] = []
    holy_people: list[dict[str, Any]] = []

    for document in documents:
        if not isinstance(document, dict):
            continue

        relative_path = str(document.get("relative_path") or "").strip()
        title = str(document.get("title") or "").strip()
        text = str(document.get("text") or "").strip()

        if relative_path == "" or title == "" or text == "":
            continue

        raw_html = str(document.get("raw_html") or "")
        citation_metadata = _citation_metadata(document)
        document_id = _stable_uuid(f"source-document:{relative_path}")
        citation_id = _stable_uuid(f"citation:{relative_path}")
        url = citation_metadata.get("apaurl") or citation_metadata.get("mlaurl") or _new_advent_url(relative_path)

        source_documents.append(
            {
                "id": document_id,
                "source_id": SOURCE_ID,
                "title": title,
                "slug": _slug_from_path(relative_path),
                "author": citation_metadata.get("apaauthor") or citation_metadata.get("mlaauthor"),
                "edition": "New Advent local HTML",
                "language": "en",
                "url": url,
                "raw_text": text,
                "checksum": hashlib.sha256(raw_html.encode("utf-8")).hexdigest(),
                "metadata": {
                    "relative_path": relative_path,
                    "meta_description": document.get("meta_description"),
                    "html_sha256": hashlib.sha256(raw_html.encode("utf-8")).hexdigest(),
                    "citation": citation_metadata,
                },
            }
        )

        citations.append(
            {
                "id": citation_id,
                "source_id": SOURCE_ID,
                "title": title,
                "locator": _locator(citation_metadata),
                "url": url,
                "excerpt": excerpt(text),
                "accessed_at": None,
                "metadata": {
                    "source_document_id": document_id,
                    "relative_path": relative_path,
                },
            }
        )

        holy_person = _holy_person_from_source_document(title, text, document_id, citation_id, relative_path, url)

        if holy_person is not None:
            holy_people.append(holy_person)

    _dedupe_holy_person_slugs(holy_people)

    return {
        "tables": {
            "sources": [source],
            "source_documents": source_documents,
            "citations": citations,
            "holy_people": holy_people,
        },
        "counts": {
            "sources": 1,
            "source_documents": len(source_documents),
            "citations": len(citations),
            "holy_people": len(holy_people),
        },
    }


def write_db_ready_payload(input_path: Path, output_path: Path) -> None:
    payload = json.loads(input_path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict):
        raise ValueError("Input JSON root must be an object")

    db_ready = build_db_ready_payload(payload)

    if output_path.suffix == ".json":
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(
            json.dumps(db_ready, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )

        return

    write_db_ready_directory(db_ready, output_path)


def write_db_ready_directory(db_ready: dict[str, Any], output_dir: Path) -> None:
    tables = db_ready.get("tables")

    if not isinstance(tables, dict):
        raise ValueError("DB-ready payload must include a tables object")

    output_dir.mkdir(parents=True, exist_ok=True)

    file_map = {
        "sources": "sources.json",
        "source_documents": "source-documents.json",
        "citations": "citations.json",
        "holy_people": "holy-people.json",
    }

    for table, filename in file_map.items():
        rows = tables.get(table, [])

        if not isinstance(rows, list):
            raise ValueError(f"DB-ready payload tables.{table} must be an array")

        (output_dir / filename).write_text(
            json.dumps(
                {
                    "table": table,
                    "rows": rows,
                    "count": len(rows),
                },
                indent=2,
                ensure_ascii=False,
            )
            + "\n",
            encoding="utf-8",
        )

    (output_dir / "manifest.json").write_text(
        json.dumps(
            {
                "source": "new-advent-catholic-encyclopedia",
                "tables": {
                    table: {
                        "path": filename,
                        "count": len(tables.get(table, [])),
                    }
                    for table, filename in file_map.items()
                },
            },
            indent=2,
            ensure_ascii=False,
        )
        + "\n",
        encoding="utf-8",
    )


def _citation_metadata(document: dict[str, Any]) -> dict[str, str]:
    metadata = document.get("metadata") or {}

    if not isinstance(metadata, dict):
        return {}

    citation = metadata.get("citation") or {}

    if not isinstance(citation, dict):
        return {}

    return {
        str(key): str(value)
        for key, value in citation.items()
        if value not in [None, ""]
    }


def _locator(citation_metadata: dict[str, str]) -> str | None:
    parts = [
        citation_metadata.get("mlavolume"),
        citation_metadata.get("apapublisher") or citation_metadata.get("mlapublisher"),
        citation_metadata.get("apayear") or citation_metadata.get("mlayear"),
    ]
    values = [part for part in parts if part]

    if not values:
        return None

    return ", ".join(values)


def _new_advent_url(relative_path: str) -> str:
    return f"https://www.newadvent.org/{relative_path}"


def _holy_person_from_source_document(
    title: str,
    text: str,
    source_document_id: str,
    citation_id: str,
    relative_path: str,
    url: str,
) -> dict[str, Any] | None:
    clean_title = _clean_title(title)
    person_type = _holy_person_type(clean_title)

    if person_type is None:
        return None

    primary_name = _primary_name_for_type(clean_title, person_type)
    life_dates = _extract_life_dates(text)
    gender = _infer_gender(primary_name, text)

    return {
        "id": _stable_uuid(f"holy-person:{relative_path}"),
        "type": person_type,
        "primary_name": primary_name,
        "slug": _slugify(primary_name),
        "biography": normalize_text(text),
        "birth_year": life_dates["birth"].get("year"),
        "birth_year_qualifier": life_dates["birth"].get("certainty"),
        "death_year": life_dates["death"].get("year"),
        "death_year_qualifier": life_dates["death"].get("certainty"),
        "life_dates": _format_life_dates(life_dates),
        "gender": gender,
        "canonical_status": _canonical_status_for_type(person_type),
        "is_martyr": _contains_word(text, "martyr"),
        "is_doctor": _contains_phrase(text, "Doctor of the Church"),
        "metadata": {
            "source_document_id": source_document_id,
            "citation_id": citation_id,
            "source_title": title,
            "relative_path": relative_path,
            "url": url,
            "extraction_method": "new_advent_holy_person_title_prefix",
            "confidence": 0.8,
            "birth": life_dates["birth"],
            "death": life_dates["death"],
        },
    }


def _holy_person_type(title: str) -> str | None:
    if re.match(r"^(?:St\.|Saint)\s+", title, flags=re.IGNORECASE):
        return "saint"

    if re.match(r"^(?:Bl\.|Blessed)\s+", title, flags=re.IGNORECASE):
        return "blessed"

    if re.match(r"^(?:Ven\.|Venerable)\s+", title, flags=re.IGNORECASE):
        return "venerable"

    if re.match(r"^Pope\s+", title, flags=re.IGNORECASE):
        return "pope"

    return None


def _canonical_status_for_type(person_type: str) -> str:
    if person_type in {"saint", "blessed", "venerable"}:
        return person_type

    return "unknown"


def _primary_name_for_type(title: str, person_type: str) -> str:
    if person_type == "blessed":
        return re.sub(r"^(?:Bl\.?|Blessed)\s+", "", title, count=1, flags=re.IGNORECASE).strip()

    if person_type == "venerable":
        return re.sub(r"^(?:Ven\.?|Venerable)\s+", "", title, count=1, flags=re.IGNORECASE).strip()

    return title


def _infer_gender(title: str, text: str) -> str | None:
    searchable = f"{title}\n{normalize_text(text)}".lower()
    female_score = sum(
        len(re.findall(pattern, searchable)) * weight
        for pattern, weight in [
            (r"\bshe\b", 3),
            (r"\bher\b", 3),
            (r"\bhers\b", 3),
            (r"\bvirgin\b", 2),
            (r"\babbess\b", 2),
            (r"\bnuns?\b", 2),
            (r"\bwidow\b", 2),
            (r"\bqueen\b", 1),
            (r"\bprincess\b", 1),
            (r"\bdaughter\b", 1),
            (r"\bfoundress\b", 2),
        ]
    )
    male_score = sum(
        len(re.findall(pattern, searchable)) * weight
        for pattern, weight in [
            (r"\bhe\b", 3),
            (r"\bhis\b", 3),
            (r"\bhim\b", 3),
            (r"\bpope\b", 2),
            (r"\bbishop\b", 2),
            (r"\barchbishop\b", 2),
            (r"\babbot\b", 2),
            (r"\bmonk\b", 2),
            (r"\bpriest\b", 2),
            (r"\bfather\b", 1),
        ]
    )

    if female_score > male_score:
        return "female"

    if male_score > female_score:
        return "male"

    return None


def _dedupe_holy_person_slugs(holy_people: list[dict[str, Any]]) -> None:
    slug_counts: dict[str, int] = {}

    for person in holy_people:
        slug = str(person.get("slug") or "")
        slug_counts[slug] = slug_counts.get(slug, 0) + 1

    seen: dict[str, int] = {}

    for person in holy_people:
        slug = str(person.get("slug") or "")

        if slug_counts.get(slug, 0) <= 1:
            continue

        metadata = person.get("metadata")
        relative_path = ""

        if isinstance(metadata, dict):
            relative_path = str(metadata.get("relative_path") or "")

        path_slug = _slug_from_path(relative_path)
        fallback_index = seen.get(slug, 0) + 1
        seen[slug] = fallback_index
        unique_slug = f"{slug}-{path_slug or fallback_index}"
        person["slug"] = unique_slug

        if isinstance(metadata, dict):
            metadata["slug_deduped_from"] = slug


def _slug_from_path(relative_path: str) -> str:
    return relative_path.removesuffix(".html").removesuffix(".htm").replace("/", "-")


def _slugify(value: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")

    return slug or _stable_uuid(value)


def _clean_title(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip().rstrip(".")


def _contains_word(text: str, word: str) -> bool:
    return re.search(rf"\b{re.escape(word)}\b", text, flags=re.IGNORECASE) is not None


def _contains_phrase(text: str, phrase: str) -> bool:
    return phrase.lower() in text.lower()


def _extract_life_dates(text: str) -> dict[str, dict[str, Any]]:
    opening = re.sub(r"\s+", " ", text).strip()[:1200]

    return {
        "birth": _extract_event_date(opening, ["born", "b."]),
        "death": _extract_event_date(opening, ["died", "d."]),
    }


def _extract_event_date(text: str, markers: list[str]) -> dict[str, Any]:
    for marker in markers:
        marker_pattern = rf"\b{re.escape(marker)}" if marker.endswith(".") else rf"\b{re.escape(marker)}\b"
        match = re.search(
            rf"{marker_pattern}\s+(?P<value>[^;()]{{0,160}})",
            text,
            flags=re.IGNORECASE,
        )

        if match is None:
            continue

        value = _clean_date_text(match.group("value"))
        parsed = _parse_date_value(value)

        if parsed["year"] is not None or parsed["date_text"] is not None:
            parsed["source_text"] = value
            parsed["extraction_marker"] = marker.lower()

            return parsed

    return {
        "year": None,
        "month": None,
        "day": None,
        "date_text": None,
        "place_text": None,
        "certainty": "unknown",
    }


def _format_life_dates(life_dates: dict[str, dict[str, Any]]) -> str | None:
    birth = _format_life_date(life_dates.get("birth", {}))
    death = _format_life_date(life_dates.get("death", {}))

    if birth and death:
        return f"{birth} - {death}"

    if birth:
        return f"b. {birth}"

    if death:
        return f"d. {death}"

    return None


def _format_life_date(date: dict[str, Any]) -> str | None:
    year = date.get("year")
    certainty = date.get("certainty")
    date_text = str(date.get("date_text") or "")

    if certainty == "century" and date_text:
        return date_text

    if not year:
        return None

    if certainty in {"circa", "probable"}:
        return f"c. {year}"

    return str(year)


def _parse_date_value(value: str) -> dict[str, Any]:
    certainty = "exact"

    if re.search(r"(?:\bc\.|\bca\.|\bcirca\b|\babout\b|\btowards\b)", value, flags=re.IGNORECASE):
        certainty = "circa"

    if re.search(r"\b(probably|perhaps|uncertain)\b|\?", value, flags=re.IGNORECASE):
        certainty = "probable"

    century = re.search(r"\b(?:in\s+the\s+)?(?P<century>[a-z]+|\d{1,2})(?:st|nd|rd|th)?\s+century\b", value, flags=re.IGNORECASE)

    if century is not None:
        return {
            "year": _century_to_midpoint(century.group("century")),
            "month": None,
            "day": None,
            "date_text": _clean_date_text(century.group(0)),
            "place_text": _place_text(value),
            "certainty": "century",
        }

    year_match = re.search(r"\b(?P<year>\d{3,4})\b", value)

    if year_match is None:
        return {
            "year": None,
            "month": None,
            "day": None,
            "date_text": None,
            "place_text": _place_text(value),
            "certainty": "unknown",
        }

    year = int(year_match.group("year"))
    prefix = value[: year_match.start()]
    month, day, date_text = _date_parts(prefix, year)

    return {
        "year": year,
        "month": month,
        "day": day,
        "date_text": date_text,
        "place_text": _place_text(value),
        "certainty": certainty,
    }


def _date_parts(value: str, year: int) -> tuple[int | None, int | None, str]:
    month_pattern = "|".join(MONTHS)
    match = re.search(rf"\b(?P<day>\d{{1,2}})\s+(?P<month>{month_pattern})\b", value, flags=re.IGNORECASE)

    if match is None:
        match = re.search(rf"\b(?P<month>{month_pattern})\s+(?P<day>\d{{1,2}})\b", value, flags=re.IGNORECASE)

    if match is not None:
        month_name = match.group("month").lower()
        day = int(match.group("day"))

        return MONTHS[month_name], day, f"{day} {month_name.title()}, {year}"

    circa = re.search(r"(c\.|ca\.|circa|about|towards)\s*$", value, flags=re.IGNORECASE)

    if circa is not None:
        return None, None, f"{_clean_date_text(circa.group(1))} {year}"

    return None, None, str(year)


def _place_text(value: str) -> str | None:
    candidate = re.split(
        r"\b\d{1,2}\s+(?:%s)\b|\b(?:%s)\s+\d{1,2}\b|\b(?:c\.|ca\.|circa|about|towards)?\s*\d{3,4}\b|\b(?:in\s+the\s+)?(?:[a-z]+|\d{1,2})(?:st|nd|rd|th)?\s+century\b"
        % ("|".join(MONTHS), "|".join(MONTHS)),
        value,
        maxsplit=1,
        flags=re.IGNORECASE,
    )[0]
    match = re.search(r"\b(?:at|in|near)\s+(?P<place>[^,;]+)", candidate, flags=re.IGNORECASE)

    if match is None:
        return None

    place = re.sub(r"\s+", " ", match.group("place")).strip(" ,")

    return place or None


def _clean_date_text(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip(" ,")


def _century_to_midpoint(value: str) -> int | None:
    words = {
        "first": 1,
        "second": 2,
        "third": 3,
        "fourth": 4,
        "fifth": 5,
        "sixth": 6,
        "seventh": 7,
        "eighth": 8,
        "ninth": 9,
        "tenth": 10,
        "eleventh": 11,
        "twelfth": 12,
        "thirteenth": 13,
        "fourteenth": 14,
        "fifteenth": 15,
        "sixteenth": 16,
        "seventeenth": 17,
        "eighteenth": 18,
        "nineteenth": 19,
        "twentieth": 20,
    }
    century = words.get(value.lower())

    if century is None and value.isdigit():
        century = int(value)

    if century is None:
        return None

    return (century - 1) * 100 + 50


def _stable_uuid(value: str) -> str:
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"vsearch:{value}"))
