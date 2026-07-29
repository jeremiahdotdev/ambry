from __future__ import annotations

import argparse
from pathlib import Path

from .ai_enrichment import AiEnrichmentOptions, run_ai_enrichment
from .background_removal import BackgroundRemovalOptions, run_background_removal
from .blob_upload import BlobUploadOptions, run_blob_upload
from .console_app import run_image_console
from .db_ready import write_db_ready_payload
from .images import ImageGenerationOptions, StyleContextOptions, prepare_style_context, run_image_generation
from .load_sqlite import load_db_ready_json, load_saints_json
from .new_advent import write_new_advent_payload


def _database_and_path(database: str | None, path: Path | None, *, path_name: str) -> tuple[str, Path]:
    if path is None:
        if database is None:
            raise RuntimeError(f"{path_name} is required")

        return "app", Path(database)

    return database or "app", path


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

    style_context = subparsers.add_parser(
        "prepare-image-style-context",
        help="Upload Ambry style reference images once and save reusable OpenAI file IDs.",
    )
    style_context.add_argument(
        "--output",
        type=Path,
        default=Path("storage/app/generated/openai-style-context.json"),
        help="Manifest path for uploaded OpenAI style reference file IDs.",
    )
    style_context.add_argument(
        "--style-reference",
        action="append",
        type=Path,
        default=None,
        help="Reference PNG/JPG/WebP for Ambry portrait style. May be passed more than once.",
    )
    style_context.add_argument("--force", action="store_true", help="Upload again even if manifest exists.")
    style_context.add_argument("--dry-run", action="store_true")

    generate_images = subparsers.add_parser(
        "generate-images",
        help="Generate local saint portrait PNGs from stored image_prompt values.",
    )
    generate_images.add_argument("database", nargs="?", default=None, help="Legacy database target, or the output directory when only one path is passed.")
    generate_images.add_argument(
        "output",
        nargs="?",
        type=Path,
        help="Local output directory for generated PNG and metadata files.",
    )
    generate_images.add_argument("--model", default="gpt-image-2")
    generate_images.add_argument("--size", default="800x1008")
    generate_images.add_argument("--portrait-size", default="608x1200")
    generate_images.add_argument("--quality", default="high")
    generate_images.add_argument("--background", default="auto")
    generate_images.add_argument("--portrait-webp-quality", type=int, default=86)
    generate_images.add_argument("--thumb-height", type=int, default=474)
    generate_images.add_argument(
        "--thumb-width",
        type=int,
        default=None,
        help="Legacy width-based thumbnail resize. Prefer --thumb-height.",
    )
    generate_images.add_argument("--thumb-webp-quality", type=int, default=80)
    generate_images.add_argument(
        "--design-analysis",
        choices=["model", "none"],
        default="model",
        help="Run the post-generation model call for key colors and page variant recommendation.",
    )
    generate_images.add_argument("--limit", type=int, default=1)
    generate_images.add_argument("--offset", type=int, default=0)
    generate_images.add_argument("--slug", default=None, help="Generate a specific saint by slug.")
    generate_images.add_argument(
        "--canonical-status",
        default=None,
        help="Only generate rows with this canonical_status value, e.g. saint.",
    )
    generate_images.add_argument(
        "--has-patronages",
        action="store_true",
        help="Only generate saints with at least one patronage link.",
    )
    generate_images.add_argument(
        "--style-reference",
        action="append",
        type=Path,
        default=None,
        help="Reference PNG/JPG/WebP for Ambry portrait style. May be passed more than once.",
    )
    generate_images.add_argument(
        "--no-style-references",
        action="store_true",
        help="Generate without the small default Ambry style reference image.",
    )
    generate_images.add_argument(
        "--style-context",
        type=Path,
        default=None,
        help="Manifest of uploaded OpenAI style reference file IDs from prepare-image-style-context.",
    )
    generate_images.add_argument(
        "--response-model",
        default="gpt-5.6",
        help="Mainline Responses API model used when --style-context is supplied.",
    )
    generate_images.add_argument(
        "--all",
        action="store_true",
        help="Generate every selected saint. Without this, --limit defaults to 1.",
    )
    generate_images.add_argument("--force", action="store_true", help="Regenerate existing image files.")
    generate_images.add_argument("--dry-run", action="store_true")

    remove_backgrounds = subparsers.add_parser(
        "remove-image-backgrounds",
        help="Create transparent PNG/WebP saint portraits in a separate output directory.",
    )
    remove_backgrounds.add_argument(
        "input",
        type=Path,
        help="Generated saint image directory, e.g. storage/app/generated/saints.",
    )
    remove_backgrounds.add_argument(
        "output",
        type=Path,
        help="Separate output directory for transparent cutouts.",
    )
    remove_backgrounds.add_argument(
        "--provider",
        choices=["light-bg", "rembg"],
        default="light-bg",
        help="Use the built-in light-background remover or optional rembg ML provider.",
    )
    remove_backgrounds.add_argument("--source-filename", default="original.png")
    remove_backgrounds.add_argument("--output-filename", default="cutout.png")
    remove_backgrounds.add_argument("--portrait-size", default="608x1200")
    remove_backgrounds.add_argument("--portrait-webp-quality", type=int, default=86)
    remove_backgrounds.add_argument("--thumb-height", type=int, default=474)
    remove_backgrounds.add_argument(
        "--thumb-width",
        type=int,
        default=None,
        help="Legacy width-based thumbnail resize. Prefer --thumb-height.",
    )
    remove_backgrounds.add_argument("--thumb-webp-quality", type=int, default=80)
    remove_backgrounds.add_argument("--tolerance", type=int, default=10)
    remove_backgrounds.add_argument("--transition", type=int, default=52)
    remove_backgrounds.add_argument("--feather-radius", type=float, default=0.4)
    remove_backgrounds.add_argument(
        "--no-horizontal-trim",
        action="store_true",
        help="Keep the full source width after removing the background.",
    )
    remove_backgrounds.add_argument("--trim-padding-ratio", type=float, default=0.015)
    remove_backgrounds.add_argument("--trim-min-width-ratio", type=float, default=0.0)
    remove_backgrounds.add_argument("--trim-alpha-threshold", type=int, default=8)
    remove_backgrounds.add_argument("--rembg-model", default="isnet-general-use")
    remove_backgrounds.add_argument("--slug", default=None)
    remove_backgrounds.add_argument("--limit", type=int, default=1)
    remove_backgrounds.add_argument("--offset", type=int, default=0)
    remove_backgrounds.add_argument("--all", action="store_true")
    remove_backgrounds.add_argument("--force", action="store_true")
    remove_backgrounds.add_argument("--dry-run", action="store_true")

    upload_blobs = subparsers.add_parser(
        "upload-saint-blobs",
        help="Upload transparent saint image assets to Vercel Blob and store public URLs on saints.",
    )
    upload_blobs.add_argument("database", nargs="?", default=None, help="Legacy database target, or the input directory when only one path is passed.")
    upload_blobs.add_argument(
        "input",
        nargs="?",
        type=Path,
        help="Transparent saint image directory, e.g. storage/app/generated/background-removed/saints.",
    )
    upload_blobs.add_argument("--prefix", default="saints/v1")
    upload_blobs.add_argument("--slug", default=None)
    upload_blobs.add_argument("--limit", type=int, default=1)
    upload_blobs.add_argument("--offset", type=int, default=0)
    upload_blobs.add_argument(
        "--missing-only",
        action="store_true",
        help="Only upload complete local asset folders whose DB URL columns are missing.",
    )
    upload_blobs.add_argument("--all", action="store_true")
    upload_blobs.add_argument(
        "--node-script",
        type=Path,
        default=Path("tools/pipeline/bin/upload-vercel-blob.mjs"),
    )
    upload_blobs.add_argument("--dry-run", action="store_true")

    image_console = subparsers.add_parser(
        "image-console",
        help="Interactive console for generating, processing, uploading, and syncing saint images.",
    )
    image_console.add_argument("--database", default="app")
    image_console.add_argument("--generated-dir", type=Path, default=Path("storage/app/generated/saints"))
    image_console.add_argument(
        "--processed-dir",
        type=Path,
        default=Path("storage/app/generated/background-removed/saints"),
    )
    image_console.add_argument(
        "--style-context",
        type=Path,
        default=Path("storage/app/generated/openai-style-context.json"),
    )
    image_console.add_argument("--batch-size", type=int, default=10)

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

    if args.command == "prepare-image-style-context":
        style_references = tuple(args.style_reference) if args.style_reference else (
            Path("storage/app/generated/style-references/assisi-final-small.png"),
        )
        counts = prepare_style_context(
            StyleContextOptions(
                output_path=args.output,
                style_references=style_references,
                force=args.force,
                dry_run=args.dry_run,
            )
        )
        print(
            "Image style context has "
            f"{counts['references']} references, "
            f"uploaded {counts['uploaded']}"
        )

        return 0

    if args.command == "generate-images":
        database_target, output_dir = _database_and_path(args.database, args.output, path_name="output")
        style_references = ()
        if not args.no_style_references:
            style_references = tuple(args.style_reference) if args.style_reference else (
                Path("storage/app/generated/style-references/assisi-final-small.png"),
            )

        counts = run_image_generation(
            ImageGenerationOptions(
                database_path=database_target,
                output_dir=output_dir,
                model=args.model,
                response_model=args.response_model,
                size=args.size,
                portrait_size=args.portrait_size,
                quality=args.quality,
                background=args.background,
                portrait_webp_quality=args.portrait_webp_quality,
                thumb_height=args.thumb_height,
                thumb_width=args.thumb_width,
                thumb_webp_quality=args.thumb_webp_quality,
                design_analysis=args.design_analysis,
                limit=None if args.all else args.limit,
                offset=args.offset,
                slug=args.slug,
                canonical_status=args.canonical_status,
                has_patronages=args.has_patronages,
                force=args.force,
                dry_run=args.dry_run,
                style_references=style_references,
                style_context_path=args.style_context,
            )
        )
        print(
            "Image generation selected "
            f"{counts['selected']} rows, "
            f"skipped {counts['skipped']}, "
            f"generated {counts['generated']}"
        )

        return 0

    if args.command == "remove-image-backgrounds":
        counts = run_background_removal(
            BackgroundRemovalOptions(
                input_dir=args.input,
                output_dir=args.output,
                provider=args.provider,
                source_filename=args.source_filename,
                output_filename=args.output_filename,
                portrait_size=args.portrait_size,
                portrait_webp_quality=args.portrait_webp_quality,
                thumb_height=args.thumb_height,
                thumb_width=args.thumb_width,
                thumb_webp_quality=args.thumb_webp_quality,
                tolerance=args.tolerance,
                transition=args.transition,
                feather_radius=args.feather_radius,
                trim_horizontal=not args.no_horizontal_trim,
                trim_padding_ratio=args.trim_padding_ratio,
                trim_min_width_ratio=args.trim_min_width_ratio,
                trim_alpha_threshold=args.trim_alpha_threshold,
                rembg_model=args.rembg_model,
                slug=args.slug,
                limit=None if args.all else args.limit,
                offset=args.offset,
                force=args.force,
                dry_run=args.dry_run,
            )
        )
        print(
            "Background removal selected "
            f"{counts['selected']} rows, "
            f"skipped {counts['skipped']}, "
            f"processed {counts['processed']}"
        )

        return 0

    if args.command == "upload-saint-blobs":
        database_target, input_dir = _database_and_path(args.database, args.input, path_name="input")
        counts = run_blob_upload(
            BlobUploadOptions(
                database_path=database_target,
                input_dir=input_dir,
                prefix=args.prefix,
                slug=args.slug,
                limit=None if args.all else args.limit,
                offset=args.offset,
                missing_only=args.missing_only,
                node_script=args.node_script,
                dry_run=args.dry_run,
            )
        )
        print(
            "Blob upload selected "
            f"{counts['selected']} rows, "
            f"uploaded {counts['uploaded_files']} files, "
            f"updated {counts['updated_rows']} rows, "
            f"missing {counts['missing_assets']} assets"
        )

        return 0

    if args.command == "image-console":
        return run_image_console(
            database_path=args.database,
            generated_dir=args.generated_dir,
            processed_dir=args.processed_dir,
            style_context_path=args.style_context,
            batch_size=args.batch_size,
        )

    parser.error("Unknown command")

    return 2


if __name__ == "__main__":
    raise SystemExit(main())
