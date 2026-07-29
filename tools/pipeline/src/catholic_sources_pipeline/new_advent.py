from __future__ import annotations

import html
import json
import re
from dataclasses import dataclass, field
from html.parser import HTMLParser
from pathlib import Path
from typing import Any

from .text import normalize_text


NEW_ADVENT_BASE_URL = "http://www.newadvent.org/"


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
            "pageSource": _page_source(self.title, self.relative_path, self.citation_metadata),
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


class NewAdventStructuredParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.in_main = False
        self.main_div_depth = 0
        self.skip_depth = 0
        self.blocks: list[dict[str, str]] = []
        self.current_type: str | None = None
        self.current_text: list[str] = []
        self.current_html: list[str] = []
        self.current_href: str | None = None
        self.current_links: list[dict[str, str]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attrs_dict = {key: value for key, value in attrs}

        if attrs_dict.get("id") == "springfield2":
            self.in_main = True
            self.main_div_depth = 1
            return

        if not self.in_main:
            return

        if tag == "div":
            if attrs_dict.get("class") == "pub":
                self.skip_depth = 1
                self._finish_block()
                return
            if self.skip_depth:
                self.skip_depth += 1
                return
            self.main_div_depth += 1

        if self.skip_depth:
            if tag not in {"br", "img", "meta", "link"}:
                self.skip_depth += 1
            return

        if tag in {"h1", "h2", "h3"}:
            self._start_block("heading")
        elif tag == "p":
            self._start_block("paragraph")
        elif tag == "blockquote":
            self._start_block("quote")
        elif tag == "li":
            self._start_block("list_item")
        elif tag == "br" and self.current_type:
            self.current_text.append("\n")
            self.current_html.append("<br>")
        elif tag == "a" and self.current_type:
            self.current_href = _new_advent_url(attrs_dict.get("href"))

    def handle_endtag(self, tag: str) -> None:
        if not self.in_main:
            return

        if self.skip_depth:
            if tag not in {"br", "img", "meta", "link"}:
                self.skip_depth -= 1
            return

        if tag == "a":
            self.current_href = None
        elif tag in {"h1", "h2", "h3", "p", "blockquote", "li"}:
            self._finish_block()
        elif tag == "div":
            self._finish_block()
            self.main_div_depth -= 1

            if self.main_div_depth <= 0:
                self.in_main = False

    def handle_data(self, data: str) -> None:
        if not self.in_main or self.skip_depth or not self.current_type:
            return

        self.current_text.append(data)
        escaped = html.escape(data)

        if self.current_href:
            self.current_html.append(f'<a href="{html.escape(self.current_href, quote=True)}">{escaped}</a>')
            self.current_links.append(
                {
                    "text": normalize_text(data),
                    "url": self.current_href,
                }
            )
        else:
            self.current_html.append(escaped)

    def _start_block(self, block_type: str) -> None:
        self._finish_block()
        self.current_type = block_type
        self.current_text = []
        self.current_html = []
        self.current_href = None

    def _finish_block(self) -> None:
        if not self.current_type:
            return

        text = normalize_text(" ".join(self.current_text))
        body_html = _normalize_inline_html("".join(self.current_html))

        if text:
            self.blocks.append(
                {
                    "type": self.current_type,
                    "text": text,
                    "html": body_html,
                    "links": self.current_links,
                }
            )

        self.current_type = None
        self.current_text = []
        self.current_html = []
        self.current_href = None
        self.current_links = []


def parse_new_advent_sections(path: Path, source_marker: str = "source:1") -> list[dict[str, Any]]:
    parser = NewAdventStructuredParser()
    raw_html = path.read_text(encoding="utf-8", errors="replace")
    parser.feed(raw_html)
    parser._finish_block()
    document = parse_new_advent_document(path, _new_advent_root_for(path))
    page_source = _page_source(
        document.title if document else path.stem,
        path.relative_to(_new_advent_root_for(path)).as_posix(),
        document.citation_metadata if document else {},
    )

    sections: list[dict[str, Any]] = []
    current: dict[str, Any] | None = None

    for block in parser.blocks:
        if block["type"] == "heading":
            if block["text"].strip().lower().startswith("st. "):
                continue
            if current and current["blocks"]:
                sections.append(_section_from_blocks(current, source_marker, page_source))

            current = _new_section(block["text"])
            continue

        if current is None:
            current = _new_section(None)

        current["blocks"].append(block)

    if current and current["blocks"]:
        sections.append(_section_from_blocks(current, source_marker, page_source))

    if not any(section.get("kind") == "sources" for section in sections):
        sections.append(
            {
                "heading": "Sources",
                "kind": "sources",
                "body": "",
                "body_html": "",
                "links": [],
                "source_markers": [source_marker],
                "sources": [],
                "pageSource": page_source,
            }
        )

    return sections


def _section_from_blocks(
    section: dict[str, Any],
    source_marker: str,
    page_source: dict[str, str | None],
) -> dict[str, Any]:
    blocks = section["blocks"]
    body = "\n\n".join(block["text"] for block in blocks).strip()
    body_html = "\n\n".join(block["html"] for block in blocks).strip()
    kind = section["kind"]

    payload = {
        "heading": section["heading"],
        "kind": kind,
        "body": body,
        "body_html": body_html,
        "links": [
            link
            for block in blocks
            for link in block.get("links", [])
            if link.get("text") and link.get("url")
        ],
        "source_markers": [source_marker],
    }

    if kind == "sources":
        payload["sources"] = _source_entries(body, body_html)
        payload["pageSource"] = page_source

    return payload


def _source_entries(text: str, body_html: str) -> list[dict[str, str]]:
    text = re.sub(r"\bThe most noteworthy works of later years are\s+", "", text)
    body_html = re.sub(r"\bThe most noteworthy works of later years are\s+", "", body_html)
    text_entries = _split_source_entries(text)
    html_entries = _split_source_entries(body_html)

    return [
        {
            "text": text_entry,
            "html": html_entries[index] if index < len(html_entries) else html.escape(text_entry),
        }
        for index, text_entry in enumerate(text_entries)
    ]


def _split_source_entries(text: str) -> list[str]:
    entries: list[str] = []

    for segment in re.split(r";\s*", text):
        entries.extend(
            part.strip()
            for part in re.split(r"(?<!St\.)(?<!Dr\.)(?<!Rev\.)(?<=\.)\s+(?=(?:The\s+[A-Z]|[A-Z][A-Z]{2,}|[A-Z][a-z]+,\s))", segment)
            if part.strip()
        )

    return entries


def _new_section(heading: str | None) -> dict[str, Any]:
    return {
        "heading": heading,
        "kind": "sources" if heading and heading.strip().lower() == "sources" else "body",
        "blocks": [],
    }


def _normalize_inline_html(value: str) -> str:
    value = re.sub(r"\s+", " ", value)
    value = re.sub(r"\s*<br>\s*", "<br>", value)

    return value.strip()


def _new_advent_url(href: str | None) -> str | None:
    if not href:
        return None

    if href.startswith("http://") or href.startswith("https://"):
        return href

    while href.startswith("../"):
        href = href[3:]

    return NEW_ADVENT_BASE_URL + href.lstrip("/")


def _new_advent_root_for(path: Path) -> Path:
    for parent in [path.parent, *path.parents]:
        if parent.name == "newadvent":
            return parent

    if path.parent.name == "cathen":
        return path.parent.parent

    return path.parent


def _page_source(
    title: str,
    relative_path: str,
    citation_metadata: dict[str, str],
) -> dict[str, str | None]:
    return {
        "title": "New Advent",
        "article": title.strip() or None,
        "locator": relative_path.replace("/", "-").removesuffix(".htm").removesuffix(".html"),
        "url": _clean_new_advent_url(citation_metadata.get("apaurl")) or _new_advent_url(relative_path),
    }


def _clean_new_advent_url(value: str | None) -> str | None:
    if not value:
        return None

    return value.strip().replace("http: //", "http://").replace("https: //", "https://") or None


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
