from __future__ import annotations

import argparse
from pathlib import Path

from .ai_enrichment import AiEnrichmentOptions, run_ai_enrichment
from .db_ready import write_db_ready_payload
from .load_sqlite import load_db_ready_json, load_saints_json
from .new_advent import write_new_advent_payload


def main() -> int:
    parser = argparse.ArgumentParser(
        prog="catholic_sources_pipeline",
        description="Convert local Catholic source HTML files into JSON.",
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    new_advent = subparsers.add_parser(
        "new-advent-html",
        help="Read a local New Advent folder and convert HTML files to JSON documents.",
    )
    new_advent.add_argument("root", type=Path, help="Path to local New Advent root, e.g. ../newadvent")
    new_advent.add_argument("output", type=Path)
    new_advent.add_argument("--subdir", default="cathen", help="Subdirectory under root to convert.")
    new_advent.add_argument("--limit", type=int, default=None, help="Optional max article count for trial runs.")

    db_ready = subparsers.add_parser(
        "db-ready",
        help="Convert HTML JSON documents into split table-shaped JSON records.",
    )
    db_ready.add_argument("input", type=Path)
    db_ready.add_argument("output", type=Path, help="Output directory, e.g. build/structured")

    load_sqlite = subparsers.add_parser(
        "load-sqlite",
        help="Load DB-ready source JSON into a local SQLite database.",
    )
    load_sqlite.add_argument("input", type=Path)
    load_sqlite.add_argument("database", type=Path)

    load_saints_sqlite = subparsers.add_parser(
        "load-saints-sqlite",
        help="Load saint rows from split DB-ready holy people JSON into a local SQLite database.",
    )
    load_saints_sqlite.add_argument("input", type=Path)
    load_saints_sqlite.add_argument("database", type=Path)
    load_saints_sqlite.add_argument(
        "--review-input",
        type=Path,
        default=None,
        help="Optional AI review JSON to merge into the SQLite holy people tables.",
    )

    ai_enrich = subparsers.add_parser(
        "ai-enrich",
        help="Use OpenAI to validate candidate saints and extract enriched metadata.",
    )
    ai_enrich.add_argument("input", type=Path, help="Split DB-ready holy people JSON file.")
    ai_enrich.add_argument(
        "--review-output",
        type=Path,
        default=Path("build/enriched/holy-people-reviews.json"),
        help="Output JSON for AI saint validation and keyword rows.",
    )
    ai_enrich.add_argument("--model", default="gpt-4.1-mini")
    ai_enrich.add_argument("--limit", type=int, default=None)
    ai_enrich.add_argument("--offset", type=int, default=0)
    ai_enrich.add_argument("--batch-size", type=int, default=10)
    ai_enrich.add_argument("--dry-run", action="store_true")

    args = parser.parse_args()

    if args.command == "new-advent-html":
        write_new_advent_payload(args.root, args.output, args.subdir, args.limit)
        print(f"Wrote New Advent HTML JSON to {args.output}")

        return 0

    if args.command == "db-ready":
        write_db_ready_payload(args.input, args.output)
        print(f"Wrote DB-ready JSON to {args.output}")

        return 0

    if args.command == "load-sqlite":
        counts = load_db_ready_json(args.input, args.database)
        print(
            "Loaded "
            f"{counts['sources']} sources, "
            f"{counts['source_documents']} source documents, "
            f"{counts['citations']} citations into {args.database}"
        )

        return 0

    if args.command == "load-saints-sqlite":
        counts = load_saints_json(args.input, args.database, review_input_path=args.review_input)
        print(
            f"Loaded {counts['saints']} holy people, "
            f"{counts['patronages']} patronage links, "
            f"{counts['feast_days']} feast days into {args.database}"
        )

        return 0

    if args.command == "ai-enrich":
        counts = run_ai_enrichment(
            AiEnrichmentOptions(
                input_path=args.input,
                review_output_path=args.review_output,
                model=args.model,
                limit=args.limit,
                offset=args.offset,
                batch_size=args.batch_size,
                dry_run=args.dry_run,
            )
        )
        print(
            "AI enrichment selected "
            f"{counts['selected']} rows, "
            f"reviewed {counts['reviewed']} "
            f"in {counts['requests']} requests"
        )

        return 0

    parser.error("Unknown command")

    return 2


if __name__ == "__main__":
    raise SystemExit(main())
