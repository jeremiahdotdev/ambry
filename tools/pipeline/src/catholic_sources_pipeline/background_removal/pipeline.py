from __future__ import annotations

import json
from collections import deque
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from catholic_sources_pipeline.logging import log


@dataclass(frozen=True)
class BackgroundRemovalOptions:
    input_dir: Path
    output_dir: Path
    provider: str = "light-bg"
    source_filename: str = "original.png"
    output_filename: str = "cutout.png"
    portrait_size: str = "608x1200"
    portrait_webp_quality: int = 86
    thumb_height: int = 474
    thumb_width: int | None = None
    thumb_webp_quality: int = 80
    tolerance: int = 40
    transition: int = 1
    feather_radius: float = 0.0
    trim_horizontal: bool = True
    trim_padding_ratio: float = 0.015
    trim_min_width_ratio: float = 0.0
    trim_alpha_threshold: int = 8
    trim_min_alpha_pixels: int = 24
    trim_min_alpha_coverage_ratio: float = 0.02
    rembg_model: str = "isnet-general-use"
    slug: str | None = None
    limit: int | None = 1
    offset: int = 0
    force: bool = False
    dry_run: bool = False


def run_background_removal(options: BackgroundRemovalOptions) -> dict[str, int]:
    sources = _select_sources(options)
    selected = [
        source
        for source in sources
        if options.force or not _output_image_path(options, source).exists()
    ]
    log(
        "Background removal queue: "
        f"{len(selected)} selected, {len(sources) - len(selected)} skipped existing"
    )

    if options.dry_run:
        for source in selected:
            log(f"{source.name}: {source / options.source_filename} -> {_output_image_path(options, source)}")

        return {
            "selected": len(sources),
            "skipped": len(sources) - len(selected),
            "processed": 0,
        }

    _ensure_pillow()
    session = _new_rembg_session(options) if options.provider == "rembg" else None
    processed = 0
    log(f"Background removal started: provider={options.provider}")

    for index, source in enumerate(selected, start=1):
        output_image_path = _output_image_path(options, source)
        output_image_path.parent.mkdir(parents=True, exist_ok=True)
        source_image_path = source / options.source_filename
        log(f"[{index}/{len(selected)}] Removing background for {source.name}")

        if options.provider == "light-bg":
            _remove_light_background(source_image_path, output_image_path, options)
        elif options.provider == "rembg":
            _remove_with_rembg(source_image_path, output_image_path, options, session)
        else:
            raise ValueError(f"Unknown background removal provider: {options.provider}")

        _trim_horizontal_alpha(output_image_path, output_image_path, options)
        derivatives = _write_webp_derivatives(output_image_path, options)
        _write_metadata(source, output_image_path, derivatives, options)
        processed += 1
        log(f"[{index}/{len(selected)}] Wrote transparent assets: {output_image_path.parent}")

    return {
        "selected": len(sources),
        "skipped": len(sources) - len(selected),
        "processed": processed,
    }


def _select_sources(options: BackgroundRemovalOptions) -> list[Path]:
    if not options.input_dir.exists():
        raise FileNotFoundError(f"Generated saint image directory not found: {options.input_dir}")

    if options.slug:
        source = options.input_dir / options.slug

        return [source] if (source / options.source_filename).exists() else []

    sources = [
        path
        for path in sorted(options.input_dir.iterdir())
        if path.is_dir() and (path / options.source_filename).exists()
    ]

    if options.offset:
        sources = sources[options.offset:]

    if options.limit is not None:
        sources = sources[:options.limit]

    return sources


def _remove_light_background(source_path: Path, output_path: Path, options: BackgroundRemovalOptions) -> None:
    from PIL import Image, ImageChops, ImageFilter

    with Image.open(source_path) as image:
        rgba = image.convert("RGBA")
        rgb = rgba.convert("RGB")
        background = _estimate_background_rgb(rgb)
        background_image = Image.new("RGB", rgb.size, background)
        difference = ImageChops.difference(rgb, background_image).convert("L")
        edge_background = _edge_connected_background_mask(difference, options.tolerance + options.transition)
        alpha = _background_alpha(difference, edge_background, options)

        if options.feather_radius > 0:
            alpha = alpha.filter(ImageFilter.GaussianBlur(options.feather_radius))
            alpha = alpha.point(lambda value: 0 if value <= 3 else value)

        alpha = _keep_non_edge_pixels_opaque(alpha, edge_background)

        result = rgba.copy()
        result.putalpha(alpha)

        # Keep fully transparent pixels visually clean for better PNG compression and easier inspection.
        transparent_bg = Image.new("RGBA", result.size, (255, 255, 255, 0))
        transparent_bg.alpha_composite(result)
        transparent_bg.save(output_path, "PNG", optimize=True)


def _edge_connected_background_mask(difference: Any, threshold: int) -> bytearray:
    width, height = difference.size
    values = difference.load()
    visited = bytearray(width * height)
    queue: deque[tuple[int, int]] = deque()

    def enqueue(x: int, y: int) -> None:
        index = y * width + x

        if visited[index] or values[x, y] > threshold:
            return

        visited[index] = 1
        queue.append((x, y))

    for x in range(width):
        enqueue(x, 0)

    for y in range(height):
        enqueue(0, y)
        enqueue(width - 1, y)

    corner_span = max(1, width // 8)

    for x in range(corner_span):
        enqueue(x, height - 1)
        enqueue(width - 1 - x, height - 1)

    while queue:
        x, y = queue.popleft()

        if x > 0:
            enqueue(x - 1, y)

        if x + 1 < width:
            enqueue(x + 1, y)

        if y > 0:
            enqueue(x, y - 1)

        if y + 1 < height:
            enqueue(x, y + 1)

    return visited


def _background_alpha(difference: Any, edge_background: bytearray, options: BackgroundRemovalOptions) -> Any:
    from PIL import Image

    width, height = difference.size
    difference_values = difference.tobytes()
    transition = max(1, options.transition)
    alpha_values = bytearray(width * height)

    for index, value in enumerate(difference_values):
        if not edge_background[index]:
            alpha_values[index] = 255
            continue

        alpha_values[index] = max(
            0,
            min(255, round(((value - options.tolerance) / transition) * 255)),
        )

    return Image.frombytes("L", (width, height), bytes(alpha_values))


def _keep_non_edge_pixels_opaque(alpha: Any, edge_background: bytearray) -> Any:
    from PIL import Image

    width, height = alpha.size
    alpha_values = bytearray(alpha.tobytes())

    for index, is_edge_background in enumerate(edge_background):
        if not is_edge_background:
            alpha_values[index] = 255

    return Image.frombytes("L", (width, height), bytes(alpha_values))


def _estimate_background_rgb(image: Any) -> tuple[int, int, int]:
    from PIL import ImageStat

    width, height = image.size
    strip = max(4, min(width, height) // 40)
    samples = [
        image.crop((0, 0, width, strip)),
        image.crop((0, height - strip, width, height)),
        image.crop((0, 0, strip, height)),
        image.crop((width - strip, 0, width, height)),
    ]
    merged = samples[0]

    for sample in samples[1:]:
        merged = _append_right(merged, sample)

    median = ImageStat.Stat(merged).median

    return tuple(int(value) for value in median[:3])


def _append_right(left: Any, right: Any) -> Any:
    from PIL import Image

    result = Image.new("RGB", (left.width + right.width, max(left.height, right.height)), (255, 255, 255))
    result.paste(left, (0, 0))
    result.paste(right, (left.width, 0))

    return result


def _new_rembg_session(options: BackgroundRemovalOptions) -> Any:
    try:
        from rembg import new_session
    except ImportError as exc:
        raise RuntimeError(
            "The rembg provider requires optional background-removal dependencies. "
            "Install them with `pip install -e 'tools/pipeline[background-removal]'`."
        ) from exc

    return new_session(options.rembg_model)


def _remove_with_rembg(
    source_path: Path,
    output_path: Path,
    options: BackgroundRemovalOptions,
    session: Any,
) -> None:
    try:
        from rembg import remove
    except ImportError as exc:
        raise RuntimeError(
            "The rembg provider requires optional background-removal dependencies. "
            "Install them with `pip install -e 'tools/pipeline[background-removal]'`."
        ) from exc

    output = remove(
        source_path.read_bytes(),
        session=session,
        alpha_matting=True,
        alpha_matting_foreground_threshold=240,
        alpha_matting_background_threshold=10,
        alpha_matting_erode_size=10,
    )
    output_path.write_bytes(output)


def _trim_horizontal_alpha(source_path: Path, output_path: Path, options: BackgroundRemovalOptions) -> None:
    if not options.trim_horizontal:
        return

    from PIL import Image

    with Image.open(source_path) as image:
        rgba = image.convert("RGBA")
        bounds = _horizontal_alpha_bounds(
            rgba,
            options.trim_alpha_threshold,
            options.trim_min_alpha_pixels,
            options.trim_min_alpha_coverage_ratio,
        )

        if bounds is None:
            rgba.save(output_path, "PNG", optimize=True)
            return

        left, right = bounds
        width, height = rgba.size
        visible_width = right - left
        padding = round(width * max(0, options.trim_padding_ratio))
        min_width = round(width * max(0, min(1, options.trim_min_width_ratio)))
        crop_width = max(visible_width + (padding * 2), min_width)
        crop_width = min(width, crop_width)
        center = (left + right) / 2
        crop_left = round(center - (crop_width / 2))
        crop_left = max(0, min(width - crop_width, crop_left))
        crop_right = crop_left + crop_width

        if crop_left == 0 and crop_right == width:
            rgba.save(output_path, "PNG", optimize=True)
            return

        rgba.crop((crop_left, 0, crop_right, height)).save(output_path, "PNG", optimize=True)


def _horizontal_alpha_bounds(
    image: Any,
    alpha_threshold: int,
    min_alpha_pixels: int,
    min_alpha_coverage_ratio: float,
) -> tuple[int, int] | None:
    alpha = image.getchannel("A")
    width, height = alpha.size
    values = alpha.load()
    threshold = max(0, min(255, alpha_threshold))
    min_visible_pixels = max(
        1,
        min(height, min_alpha_pixels),
        round(height * max(0, min(1, min_alpha_coverage_ratio))),
    )
    left = None
    right = None

    for x in range(width):
        visible_pixels = 0

        for y in range(height):
            if values[x, y] > threshold:
                visible_pixels += 1

                if visible_pixels >= min_visible_pixels:
                    left = x
                    break

        if left is not None:
            break

    for x in range(width - 1, -1, -1):
        visible_pixels = 0

        for y in range(height):
            if values[x, y] > threshold:
                visible_pixels += 1

                if visible_pixels >= min_visible_pixels:
                    right = x + 1
                    break

        if right is not None:
            break

    if left is None or right is None or right <= left:
        return None

    return left, right


def _write_webp_derivatives(image_path: Path, options: BackgroundRemovalOptions) -> dict[str, dict[str, Any]]:
    from PIL import Image

    portrait_path = image_path.parent / "portrait.webp"
    thumb_path = image_path.parent / "thumb.webp"

    with Image.open(image_path) as image:
        _, portrait_height = _parse_size(options.portrait_size)
        portrait = _resize_to_height(image.convert("RGBA"), portrait_height)
        portrait = _clamp_alpha_floor(portrait)
        portrait.save(portrait_path, "WEBP", quality=options.portrait_webp_quality, method=6)

        thumb_height = options.thumb_height

        if options.thumb_width is not None:
            width, height = portrait.size
            thumb_height = round(height * (options.thumb_width / width))

        thumb = _resize_to_height(portrait, thumb_height)
        thumb = _clamp_alpha_floor(thumb)
        thumb.save(thumb_path, "WEBP", quality=options.thumb_webp_quality, method=6)

    return {
        "cutout": _file_metadata(image_path),
        "portrait": _file_metadata(portrait_path),
        "thumb": _file_metadata(thumb_path),
    }


def _write_metadata(
    source_dir: Path,
    output_image_path: Path,
    derivatives: dict[str, dict[str, Any]],
    options: BackgroundRemovalOptions,
) -> None:
    source_metadata_path = source_dir / "metadata.json"
    source_metadata = {}

    if source_metadata_path.exists():
        try:
            source_metadata = json.loads(source_metadata_path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            source_metadata = {}

    metadata = {
        "slug": source_dir.name,
        "source_image": str(source_dir / options.source_filename),
        "provider": options.provider,
        "rembg_model": options.rembg_model if options.provider == "rembg" else None,
        "trim": {
            "horizontal": options.trim_horizontal,
            "padding_ratio": options.trim_padding_ratio,
            "min_width_ratio": options.trim_min_width_ratio,
            "alpha_threshold": options.trim_alpha_threshold,
            "min_alpha_pixels": options.trim_min_alpha_pixels,
            "min_alpha_coverage_ratio": options.trim_min_alpha_coverage_ratio,
        },
        "output_format": "png",
        "derivatives": derivatives,
        "background_removed_at": datetime.now(timezone.utc).isoformat(),
        "source_metadata": {
            "model": source_metadata.get("model"),
            "size": source_metadata.get("size"),
            "generated_at": source_metadata.get("generated_at"),
            "design_recommendation": source_metadata.get("design_recommendation"),
        },
    }
    output_image_path.parent.joinpath("metadata.json").write_text(
        json.dumps(metadata, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def _output_image_path(options: BackgroundRemovalOptions, source: Path) -> Path:
    return options.output_dir / source.name / options.output_filename


def _file_metadata(path: Path) -> dict[str, Any]:
    return {
        "path": str(path),
        "bytes": path.stat().st_size,
    }


def _parse_size(size: str) -> tuple[int, int]:
    try:
        width, height = size.lower().split("x", 1)

        return (int(width), int(height))
    except ValueError as exc:
        raise ValueError(f"Invalid size: {size}") from exc


def _resize_to_height(image: Any, height: int) -> Any:
    from PIL import Image

    if height <= 0:
        raise ValueError(f"Height must be positive: {height}")

    width = round(image.width * (height / image.height))

    return image.resize((width, height), Image.Resampling.LANCZOS)


def _clamp_alpha_floor(image: Any) -> Any:
    if "A" not in image.getbands():
        return image

    result = image.copy()
    alpha = result.getchannel("A").point(lambda value: 0 if value <= 3 else value)
    result.putalpha(alpha)

    return result


def _ensure_pillow() -> None:
    try:
        import PIL  # noqa: F401
    except ImportError as exc:
        raise RuntimeError(
            "Pillow is required for background removal. "
            "Install the pipeline dependencies with `pip install -e tools/pipeline`."
        ) from exc
