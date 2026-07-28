from __future__ import annotations

import html
import json
import re
from dataclasses import dataclass, field
from html.parser import HTMLParser
from pathlib import Path
from typing import Any

from .text import normalize_text


@dataclass(frozen=True)
class NewAdventDocument:
    title: str
    text: str
    raw_html: str
    relative_path: str
    meta_description: str | None = None
    citation_metadata: dict[str, str] = field(default_factory=dict)
    metadata: dict[str, Any] = field(default_factory=dict)

    def to_json(self) -> dict[str, Any]:
        metadata = dict(self.metadata)
        metadata["citation"] = self.citation_metadata

        return {
            "title": self.title,
            "relative_path": self.relative_path,
            "meta_description": self.meta_description,
            "text": self.text,
            "raw_html": self.raw_html,
            "metadata": metadata,
        }


class NewAdventHtmlParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.title_parts: list[str] = []
        self.meta_description: str | None = None
        self.in_title = False
        self.in_main = False
        self.main_div_depth = 0
        self.main_parts: list[str] = []
        self.pub_spans: dict[str, str] = {}
        self.current_span_id: str | None = None
        self.current_span_parts: list[str] = []
        self.skip_depth = 0

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attrs_dict = {key: value for key, value in attrs}

        if tag == "title":
            self.in_title = True

        if tag == "meta" and attrs_dict.get("name") == "description":
            self.meta_description = attrs_dict.get("content")

        if attrs_dict.get("id") == "springfield2":
            self.in_main = True
            self.main_div_depth = 1
            return

        if self.in_main:
            if tag == "div":
                if attrs_dict.get("class") == "pub":
                    self.skip_depth = 1
                elif self.skip_depth:
                    self.skip_depth += 1
                else:
                    self.main_div_depth += 1

            elif self.skip_depth and tag not in {"br", "img", "meta", "link"}:
                self.skip_depth += 1

            if self.skip_depth == 0 and tag in {"p", "h1", "h2", "h3", "li", "br"}:
                self.main_parts.append("\n")

            if tag == "span" and attrs_dict.get("id"):
                self.current_span_id = attrs_dict["id"]
                self.current_span_parts = []

    def handle_endtag(self, tag: str) -> None:
        if tag == "title":
            self.in_title = False

        if self.current_span_id and tag == "span":
            self.pub_spans[self.current_span_id] = normalize_text(" ".join(self.current_span_parts))
            self.current_span_id = None
            self.current_span_parts = []

        if not self.in_main:
            return

        if self.skip_depth:
            if tag not in {"br", "img", "meta", "link"}:
                self.skip_depth -= 1

            return

        if tag in {"p", "h1", "h2", "h3", "li"}:
            self.main_parts.append("\n")

        if tag == "div":
            self.main_div_depth -= 1

            if self.main_div_depth <= 0:
                self.in_main = False

    def handle_data(self, data: str) -> None:
        if self.in_title:
            self.title_parts.append(data)

        if self.current_span_id:
            self.current_span_parts.append(data)

        if self.in_main and self.skip_depth == 0:
            self.main_parts.append(data)

    def title(self) -> str:
        title = normalize_text(" ".join(self.title_parts))
        return re.sub(r"^CATHOLIC ENCYCLOPEDIA:\s*", "", title).strip()

    def body_text(self) -> str:
        return normalize_text(html.unescape(" ".join(self.main_parts)))


def parse_new_advent_document(path: Path, root: Path) -> NewAdventDocument | None:
    raw_html = path.read_text(encoding="utf-8", errors="replace")
    parser = NewAdventHtmlParser()
    parser.feed(raw_html)

    title = parser.title()
    body = parser.body_text()

    if title == "" or body == "":
        return None

    relative_path = path.relative_to(root).as_posix()

    return NewAdventDocument(
        title=parser.pub_spans.get("apaarticle") or title,
        text=body,
        raw_html=raw_html,
        relative_path=relative_path,
        meta_description=parser.meta_description,
        citation_metadata=parser.pub_spans,
        metadata={
            "provider": "New Advent",
            "extension": path.suffix.lower(),
        },
    )


def build_new_advent_payload(root: Path, subdir: str = "cathen", limit: int | None = None) -> dict[str, Any]:
    source_dir = root / subdir

    if not source_dir.is_dir():
        raise ValueError(f"New Advent source directory not found: {source_dir}")

    document_paths = sorted(
        path
        for path in source_dir.rglob("*")
        if path.is_file() and path.suffix.lower() in {".htm", ".html"}
    )

    if limit is not None:
        document_paths = document_paths[:limit]

    documents = [
        document.to_json()
        for path in document_paths
        if (document := parse_new_advent_document(path, root)) is not None
    ]

    return {
        "source": {
            "name": "New Advent local HTML",
            "root": str(root),
            "subdir": subdir,
        },
        "documents": documents,
    }


def write_new_advent_payload(
    root: Path,
    output_path: Path,
    subdir: str = "cathen",
    limit: int | None = None,
) -> None:
    payload = build_new_advent_payload(root, subdir, limit)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
