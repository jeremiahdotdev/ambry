from __future__ import annotations

import re
from dataclasses import dataclass


@dataclass(frozen=True)
class Section:
    heading: str
    body: str

    def to_dict(self) -> dict[str, str]:
        return {
            "heading": self.heading,
            "body": self.body,
        }


def normalize_text(value: str) -> str:
    lines = [re.sub(r"\s+", " ", line).strip() for line in value.splitlines()]
    paragraphs: list[str] = []
    current: list[str] = []

    for line in lines:
        if line == "":
            if current:
                paragraphs.append(" ".join(current))
                current = []
            continue

        current.append(line)

    if current:
        paragraphs.append(" ".join(current))

    return "\n\n".join(_clean_punctuation_spacing(paragraph) for paragraph in paragraphs).strip()


def _clean_punctuation_spacing(value: str) -> str:
    value = re.sub(r",\s*([;:.!?])", r"\1", value)
    value = re.sub(r"([:;!?])\s*\.", r"\1", value)
    value = re.sub(r"[,.;:]\s*([!?])", r"\1", value)
    value = re.sub(r"\s+([,.;:!?])", r"\1", value)
    value = re.sub(r"([,.;:!?])(?:\s*\1)+", r"\1", value)
    value = re.sub(r"([,;:!?])(?=\S)", r"\1 ", value)
    value = re.sub(r"\(\s+", "(", value)
    value = re.sub(r"\s+\)", ")", value)
    value = re.sub(r"\[\s+", "[", value)
    value = re.sub(r"\s+\]", "]", value)
    value = re.sub(r"\s+", " ", value)

    return value.strip()


def split_sections(text: str) -> list[Section]:
    normalized = normalize_text(text)

    if normalized == "":
        return []

    sections: list[Section] = []
    current_heading = "Article"
    current_body: list[str] = []

    for paragraph in normalized.split("\n\n"):
        if _looks_like_heading(paragraph):
            if current_body:
                sections.append(Section(current_heading, "\n\n".join(current_body).strip()))
                current_body = []

            current_heading = paragraph.strip("= ").title()
            continue

        current_body.append(paragraph)

    if current_body:
        sections.append(Section(current_heading, "\n\n".join(current_body).strip()))

    return sections


def excerpt(text: str, max_chars: int = 420) -> str:
    normalized = normalize_text(text)

    if len(normalized) <= max_chars:
        return normalized

    truncated = normalized[:max_chars].rsplit(" ", 1)[0].rstrip(".,;:")

    return f"{truncated}."


def _looks_like_heading(paragraph: str) -> bool:
    if paragraph.startswith("==") and paragraph.endswith("=="):
        return True

    if len(paragraph) > 72:
        return False

    letters = [char for char in paragraph if char.isalpha()]

    return bool(letters) and paragraph.upper() == paragraph
