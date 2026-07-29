from __future__ import annotations

from pathlib import Path

from .background_removal import BackgroundRemovalOptions, run_background_removal
from .blob_upload import BlobUploadOptions, run_blob_upload
from .database import DatabaseTarget, load_dotenv
from .images import ImageGenerationOptions, run_image_generation
from .logging import log


def run_image_console(
    *,
    database_path: DatabaseTarget,
    generated_dir: Path,
    processed_dir: Path,
    style_context_path: Path,
    batch_size: int,
) -> int:
    load_dotenv()
    log("Ambry saint image console")

    while True:
        _print_menu(batch_size)
        choice = input("Choose an action: ").strip().lower()

        if choice in {"q", "quit", "exit"}:
            log("Goodbye.")
            return 0

        if choice == "1":
            _preview_next_batch(database_path, generated_dir, style_context_path, batch_size)
        elif choice == "2":
            if _confirm(f"Generate the next {batch_size} saint images with patronages?"):
                _generate_next_batch(database_path, generated_dir, style_context_path, batch_size)
        elif choice == "3":
            force = _confirm("Reprocess existing transparent assets too?", default=False)
            _remove_all_backgrounds(generated_dir, processed_dir, force=force)
        elif choice == "4":
            _upload_missing_assets(database_path, processed_dir)
        elif choice == "5":
            if _confirm(f"Run full pipeline for the next {batch_size} saint images?"):
                _generate_next_batch(database_path, generated_dir, style_context_path, batch_size)
                _remove_all_backgrounds(generated_dir, processed_dir, force=False)
                _upload_missing_assets(database_path, processed_dir)
        else:
            log("Unknown choice. Pick 1, 2, 3, 4, 5, or q.")


def _print_menu(batch_size: int) -> None:
    print()
    print("Ambry Saint Images")
    print("-------------------")
    print("1. Preview next saint image batch")
    print(f"2. Generate next {batch_size} saint images")
    print("3. Remove backgrounds for all local originals")
    print("4. Upload missing processed assets and update DB URLs")
    print(f"5. Full pipeline for next {batch_size}")
    print("q. Quit")
    print()


def _preview_next_batch(
    database_path: DatabaseTarget,
    generated_dir: Path,
    style_context_path: Path,
    batch_size: int,
) -> None:
    run_image_generation(
        ImageGenerationOptions(
            database_path=database_path,
            output_dir=generated_dir,
            style_context_path=style_context_path,
            canonical_status="saint",
            has_patronages=True,
            limit=batch_size,
            dry_run=True,
        )
    )


def _generate_next_batch(
    database_path: DatabaseTarget,
    generated_dir: Path,
    style_context_path: Path,
    batch_size: int,
) -> None:
    run_image_generation(
        ImageGenerationOptions(
            database_path=database_path,
            output_dir=generated_dir,
            style_context_path=style_context_path,
            canonical_status="saint",
            has_patronages=True,
            limit=batch_size,
        )
    )


def _remove_all_backgrounds(generated_dir: Path, processed_dir: Path, *, force: bool) -> None:
    run_background_removal(
        BackgroundRemovalOptions(
            input_dir=generated_dir,
            output_dir=processed_dir,
            limit=None,
            force=force,
        )
    )


def _upload_missing_assets(database_path: DatabaseTarget, processed_dir: Path) -> None:
    run_blob_upload(
        BlobUploadOptions(
            database_path=database_path,
            input_dir=processed_dir,
            limit=None,
            missing_only=True,
        )
    )


def _confirm(prompt: str, *, default: bool = True) -> bool:
    suffix = "Y/n" if default else "y/N"
    answer = input(f"{prompt} [{suffix}] ").strip().lower()

    if not answer:
        return default

    return answer in {"y", "yes"}
