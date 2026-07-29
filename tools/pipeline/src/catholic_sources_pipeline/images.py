from __future__ import annotations

import base64
from contextlib import ExitStack
from decimal import Decimal
import json
import os
from dataclasses import dataclass, replace
from datetime import date, datetime, timezone
from pathlib import Path
from time import perf_counter
from typing import Any
from uuid import UUID

from .database import DatabaseTarget, database_label, ensure_sqlite_columns, execute, json_param, query_rows
from .logging import log


DEFAULT_STYLE_REFERENCES = (
    Path("storage/app/generated/style-references/assisi-final-small.png"),
)
SOURCE_STYLE_REFERENCES = (
    Path("storage/app/generated/saints/st-francis-of-assisi/image.png"),
)

PAGE_VARIANTS = {
    "classic-gold": "gold, cream, parchment, warm saint halos, traditional icons",
    "celtic-green": "deep greens, moss, Ireland, nature, pastoral saints",
    "marian-blue": "blue, ivory, silver, Marian or contemplative imagery",
    "martyr-crimson": "red, burgundy, rose-brown, martyrdom or sacrificial witness",
    "monastic-olive": "olive, muted green, brown, monastic habits, desert simplicity",
    "desert-rose": "terracotta, warm rose, sand, desert fathers and mothers",
    "bishop-plum": "burgundy, maroon, deep wine red, episcopal vestments, bishops and church authority",
    "doctor-indigo": "indigo, scholarly blue, theological depth, doctors and writers",
    "virgin-ivory": "ivory, white, pale gold, purity, virgins and contemplatives",
    "mission-teal": "teal, sea-green, missionary travel, evangelists and founders",
    "papal-cream": "cream, gold, white, papal or Roman imagery",
    "ascetic-stone": "stone, gray-green, restrained neutrals, ascetics and hermits",
    "dominican-charcoal": "black, white, charcoal, Dominican habits, penitents, severe theologians",
    "royal-red-gold": "royal red, gold, noble garments, kings, queens, imperial or courtly saints",
    "byzantine-jewel": "deep blue, ruby, emerald, gold, Eastern icons, ancient jeweled sacred art",
    "floral-rose": "rose pink, blush, soft floral devotional imagery, saints associated with roses or gentle affection",
    "sea-aqua": "aqua, sea blue, pale teal, fishermen, seafarers, coastal patrons, water symbolism",
}

DESIGN_COLUMNS: dict[str, str] = {
    "variant": "image_page_variant",
    "colors": "image_key_colors",
    "reason": "image_variant_reason",
    "confidence": "image_variant_confidence",
}


@dataclass(frozen=True)
class ImageGenerationOptions:
    database_path: DatabaseTarget
    output_dir: Path
    model: str = "gpt-image-2"
    response_model: str = "gpt-5.6"
    size: str = "800x1008"
    portrait_size: str = "608x1200"
    quality: str = "high"
    background: str = "auto"
    portrait_webp_quality: int = 86
    thumb_height: int = 474
    thumb_width: int | None = None
    thumb_webp_quality: int = 80
    design_analysis: str = "model"
    limit: int | None = 1
    offset: int = 0
    slug: str | None = None
    canonical_status: str | None = None
    has_patronages: bool = False
    force: bool = False
    dry_run: bool = False
    style_references: tuple[Path, ...] = DEFAULT_STYLE_REFERENCES
    style_context_path: Path | None = None


@dataclass(frozen=True)
class StyleContextOptions:
    output_path: Path
    style_references: tuple[Path, ...] = DEFAULT_STYLE_REFERENCES
    force: bool = False
    dry_run: bool = False


def prepare_style_context(options: StyleContextOptions) -> dict[str, int]:
    if options.dry_run:
        for path in options.style_references:
            log(f"would upload style reference: {path}")

        return {
            "uploaded": 0,
            "references": len(options.style_references),
        }

    _ensure_default_style_reference(options.style_references)
    references = _existing_style_references(options.style_references)

    if not references:
        raise FileNotFoundError("No style reference images found")

    if (
        options.output_path.exists()
        and not options.force
        and _style_context_matches(options.output_path, references)
    ):
        manifest = _read_style_context(options.output_path)

        return {
            "uploaded": 0,
            "references": len(manifest["references"]),
        }

    _ensure_api_key()

    from openai import OpenAI

    client = OpenAI()
    uploaded = []

    for path in references:
        log(f"Uploading style reference: {path}")
        with path.open("rb") as file:
            result = client.files.create(file=file, purpose="vision")

        uploaded.append(
            {
                "path": str(path),
                "file_id": result.id,
                "filename": getattr(result, "filename", path.name),
                "bytes": getattr(result, "bytes", path.stat().st_size),
            }
        )
        log(f"Uploaded {path} as {result.id}")

    options.output_path.parent.mkdir(parents=True, exist_ok=True)
    options.output_path.write_text(
        json.dumps(
            {
                "created_at": datetime.now(timezone.utc).isoformat(),
                "references": uploaded,
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )

    return {
        "uploaded": len(uploaded),
        "references": len(uploaded),
    }


def run_image_generation(options: ImageGenerationOptions) -> dict[str, int]:
    selector_options = options

    if not options.slug and not options.force and options.limit is not None:
        selector_options = replace(options, limit=None, offset=0)

    rows = _select_saints(selector_options)
    missing = [
        row
        for row in rows
        if options.force or not _image_path(options.output_dir, row["slug"]).exists()
    ]

    if not options.slug and not options.force and options.limit is not None:
        selected = missing[options.offset:options.offset + options.limit]
    else:
        selected = missing

    log(
        "Image generation queue: "
        f"{len(selected)} selected, {len(rows) - len(missing)} skipped existing, "
        f"{len(missing)} missing total, db={database_label(options.database_path)}"
    )

    if options.dry_run:
        for row in selected:
            log(f"{row['slug']}: {_image_path(options.output_dir, row['slug'])}")

        if options.style_context_path and options.style_context_path.exists():
            manifest = _read_style_context(options.style_context_path)
            for reference in manifest["references"]:
                log(f"style context file: {reference['file_id']} ({reference['path']})")
        else:
            for path in options.style_references:
                log(f"style reference: {path}")

        return {
            "selected": len(selected),
            "skipped": len(rows) - len(missing),
            "generated": 0,
        }

    _ensure_default_style_reference(options.style_references)
    _ensure_api_key()

    # Import lazily so dry-runs and tests do not require the OpenAI package.
    from openai import OpenAI

    client = OpenAI()
    generated = 0
    style_context = (
        _read_style_context(options.style_context_path)
        if options.style_context_path and options.style_context_path.exists()
        else None
    )
    log(
        "Image generation started: "
        f"model={options.model}, size={options.size}, quality={options.quality}, "
        f"style_context={'yes' if style_context else 'no'}, design_analysis={options.design_analysis}"
    )

    for index, row in enumerate(selected, start=1):
        full_prompt = build_portrait_prompt(row, options.size)
        image_path = _image_path(options.output_dir, row["slug"])
        metadata_path = _metadata_path(options.output_dir, row["slug"])
        image_path.parent.mkdir(parents=True, exist_ok=True)
        style_references = _existing_style_references(options.style_references)
        log(f"[{index}/{len(selected)}] Requesting image for {row['slug']} ({row['primary_name']})")
        request_started = perf_counter()

        if style_context:
            result = _create_response_image(client, row, full_prompt, options, style_context)
            image_base64 = result["image_base64"]
            response_metadata = result["metadata"]
        elif style_references:
            with ExitStack() as stack:
                reference_files = [
                    stack.enter_context(path.open("rb"))
                    for path in style_references
                ]
                result = client.images.edit(
                    model=options.model,
                    image=reference_files,
                    prompt=full_prompt,
                    n=1,
                    size=options.size,
                    quality=options.quality,
                    background=options.background,
                    output_format="png",
                )
            if not result.data or not result.data[0].b64_json:
                raise RuntimeError(f"OpenAI returned no image data for {row['slug']}")

            image_base64 = result.data[0].b64_json
            response_metadata = _image_api_metadata(result)
        else:
            result = client.images.generate(
                model=options.model,
                prompt=full_prompt,
                n=1,
                size=options.size,
                quality=options.quality,
                background=options.background,
                output_format="png",
            )
            if not result.data or not result.data[0].b64_json:
                raise RuntimeError(f"OpenAI returned no image data for {row['slug']}")

            image_base64 = result.data[0].b64_json
            response_metadata = _image_api_metadata(result)

        log(f"[{index}/{len(selected)}] OpenAI image returned for {row['slug']} in {_elapsed(request_started)}")
        image_path.write_bytes(base64.b64decode(image_base64))
        log(f"[{index}/{len(selected)}] Wrote original PNG: {image_path}")
        derivatives = _write_webp_derivatives(image_path, options)
        log(f"[{index}/{len(selected)}] Wrote local WebP derivatives for {row['slug']}")
        design_recommendation = None

        if options.design_analysis == "model":
            analysis_started = perf_counter()
            log(f"[{index}/{len(selected)}] Requesting design analysis for {row['slug']}")
            design_recommendation = _recommend_page_variant(
                client,
                row,
                image_path,
                options,
            )
            log(f"[{index}/{len(selected)}] Design analysis returned for {row['slug']} in {_elapsed(analysis_started)}")

        _update_saint_design_recommendation(options.database_path, row["slug"], design_recommendation)
        metadata_path.write_text(
            json.dumps(
                _metadata(
                    row,
                    full_prompt,
                    options,
                    response_metadata,
                    style_context,
                    derivatives,
                    design_recommendation,
                ),
                ensure_ascii=False,
                indent=2,
            )
            + "\n",
            encoding="utf-8",
        )
        generated += 1
        log(f"[{index}/{len(selected)}] Finished {row['slug']}")

    return {
        "selected": len(selected),
        "skipped": len(rows) - len(missing),
        "generated": generated,
    }


def _update_saint_design_recommendation(
    database_path: DatabaseTarget,
    slug: str,
    design_recommendation: dict[str, Any] | None,
) -> None:
    if not design_recommendation:
        return

    variant = design_recommendation.get("recommended_page_variant")

    if variant not in PAGE_VARIANTS:
        return

    ensure_sqlite_columns(database_path, DESIGN_COLUMNS, dry_run=False)
    execute(
        database_path,
        """
        update saints
        set image_page_variant = ?,
            image_key_colors = ?,
            image_variant_reason = ?,
            image_variant_confidence = ?,
            updated_at = current_timestamp
        where slug = ?
        """,
        (
            variant,
            json_param(database_path, design_recommendation.get("key_colors") or []),
            design_recommendation.get("variant_reason"),
            design_recommendation.get("confidence"),
            slug,
        ),
    )
    log(f"Updated DB design recommendation for {slug}: {variant}")


def _elapsed(started: float) -> str:
    return f"{perf_counter() - started:.1f}s"


def build_portrait_prompt(row: dict[str, Any], size: str | None = None) -> str:
    life_dates = row.get("life_dates")
    requested_width, requested_height = _parse_size(size) if size else (None, None)
    context = [
        f"Subject: {row['primary_name']}.",
        f"Canonical status: {row.get('canonical_status') or 'holy person'}.",
    ]

    if life_dates:
        context.append(f"Life dates or era: {life_dates}.")

    if row.get("gender"):
        context.append(f"Gender presentation: {row['gender']}.")

    context.append(f"Portrait brief: {row['image_prompt']}")

    canvas = ""

    if requested_width and requested_height:
        canvas = (
            f"The final image canvas must be exactly {requested_width} pixels wide by "
            f"{requested_height} pixels tall, matching a {requested_width}:{requested_height} aspect ratio. "
            "Do not return a square image, a 500x1000 image, or any alternate dimensions. "
        )

    style = (
        "Create a reverent Catholic saint portrait for a search/profile interface. "
        f"{canvas}"
        "Use the attached Ambry reference images for style only, not subject identity: "
        "Do not copy the face, expression, hairline, beard shape, or identity of the reference image. "
        "Use geometric angles for the face, mirroring the reference style, but do not copy the reference face. "
        "flat icon-like devotional illustration, clean geometric robe folds, translucent layered fabric, "
        "crisp white contour lines, subtle paper texture, restrained shading, large circular halo, "
        "Make the face distinct to this saint: use known historical or traditional iconographic traits, era, age, ethnicity, "
        "hair, beard, expression, and facial structure when available, while staying in the same illustrated collection style. "
        "Avoid generic repeated faces across saints. "
        "a plain very light removable background with no scenery, no texture, no shadow, and no painted backdrop, "
        "and a calm frontal sacred-art pose. Keep the figure edges clean for later background removal. "
        "Create a proportional full-body standing portrait with the figure centered and feet visible. "
        "The saint must have normal human proportions: natural head-to-body ratio, normal shoulder width, normal torso length, "
        "normal leg length, and a grounded stance. Do not elongate, stretch, narrow, or stylize the body into a tall icon column. "
        "Robes and vestments should drape over a believable human body, not form an unnaturally long vertical tube. "
        "Show the complete head, shoulders, torso, waist, hips, legs, and feet; the lower body must be visibly present. "
        "Do not crop at the waist, hips, mid-torso, knees, ankles, or feet. "
        "Keep the bottom half visually clean: below the waist, show only the robe/body crop and simple empty space. "
        "Do not place animals, plants, books, staffs, scrolls, buildings, landscapes, ground shadows, or other props/entities in the bottom half. "
        "Any saint symbols, animals, or props must be small and kept in the upper half near the shoulders, hands, or halo. "
        "Let historically meaningful colors and symbols vary by saint, while preserving the collection style. "
        "Use a clear, harmonious color palette that can later map to one Ambry page variant. "
        "Do not include text, captions, watermarks, UI elements, extra people, modern objects, "
        "or exaggerated fantasy styling. Avoid photorealistic celebrity likenesses."
    )

    return "\n".join([*context, style])


def _write_webp_derivatives(image_path: Path, options: ImageGenerationOptions) -> dict[str, dict[str, Any]]:
    try:
        from PIL import Image
    except ImportError as exc:
        raise RuntimeError(
            "Pillow is required to write WebP derivatives. "
            "Install the pipeline dependencies with `pip install -e tools/pipeline`."
        ) from exc

    portrait_path = _portrait_path(image_path.parent)
    thumb_path = _thumb_path(image_path.parent)

    with Image.open(image_path) as image:
        _, portrait_height = _parse_size(options.portrait_size)
        portrait = _resize_to_height(image, portrait_height)
        portrait.save(portrait_path, "WEBP", quality=options.portrait_webp_quality, method=6)

        thumb_height = options.thumb_height

        if options.thumb_width is not None:
            width, height = portrait.size
            thumb_height = round(height * (options.thumb_width / width))

        thumb = _resize_to_height(portrait, thumb_height)
        thumb.save(thumb_path, "WEBP", quality=options.thumb_webp_quality, method=6)

    return {
        "original": _file_metadata(image_path),
        "portrait": _file_metadata(portrait_path),
        "thumb": _file_metadata(thumb_path),
    }


def _select_saints(options: ImageGenerationOptions) -> list[dict[str, Any]]:
    filters = [
        "image_prompt is not null",
        "trim(image_prompt) != ''",
    ]
    params: list[Any] = []

    if options.slug:
        filters.append("slug = ?")
        params.append(options.slug)

    if options.canonical_status:
        filters.append("lower(canonical_status) = lower(?)")
        params.append(options.canonical_status)

    if options.has_patronages:
        filters.append(
            "exists (select 1 from patronage_saint where patronage_saint.saint_id = saints.id)"
        )

    limit_sql = "" if options.limit is None or options.slug else "limit ? offset ?"

    if options.limit is not None:
        if not options.slug:
            params.extend([options.limit, options.offset])

    return query_rows(
        options.database_path,
        f"""
        select id, primary_name, slug, life_dates, gender, canonical_status, image_prompt
        from saints
        where {' and '.join(filters)}
        order by slug
        {limit_sql}
        """,
        params,
    )


def _metadata(
    row: dict[str, Any],
    full_prompt: str,
    options: ImageGenerationOptions,
    response_metadata: dict[str, Any],
    style_context: dict[str, Any] | None,
    derivatives: dict[str, dict[str, Any]],
    design_recommendation: dict[str, Any] | None,
) -> dict[str, Any]:
    return _jsonable({
        "saint_id": row["id"],
        "slug": row["slug"],
        "primary_name": row["primary_name"],
        "model": options.model,
        "response_model": options.response_model if style_context else None,
        "size": options.size,
        "quality": options.quality,
        "output_format": "png",
        "derivatives": derivatives,
        "design_recommendation": design_recommendation,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source_image_prompt": row["image_prompt"],
        "full_prompt": full_prompt,
        "style_references": [str(path) for path in _existing_style_references(options.style_references)],
        "style_context_file_ids": [
            reference["file_id"]
            for reference in style_context["references"]
        ] if style_context else [],
        **response_metadata,
    })


def _recommend_page_variant(
    client: Any,
    row: dict[str, Any],
    image_path: Path,
    options: ImageGenerationOptions,
) -> dict[str, Any]:
    prompt = (
        "Inspect this generated Ambry saint portrait and return ONLY valid JSON. "
        "Choose the best page variant so decorative circles and page chrome harmonize with the portrait. "
        "Use one of these variants exactly:\n"
        f"{json.dumps(PAGE_VARIANTS, ensure_ascii=False, indent=2)}\n"
        "JSON shape: "
        '{"key_colors":[{"name":"string","hex":"#rrggbb","role":"dominant|accent|halo|robe|symbol"}],'
        '"recommended_page_variant":"variant-name","variant_reason":"short reason",'
        '"confidence":0.0}. '
        f"Saint: {row['primary_name']}. "
        f"Status: {row.get('canonical_status') or 'holy person'}. "
        "Prefer the portrait's robe/accent/symbol colors over the removable light background."
    )
    encoded = base64.b64encode(image_path.read_bytes()).decode("ascii")
    response = client.responses.create(
        model=options.response_model,
        input=[
            {
                "role": "user",
                "content": [
                    {"type": "input_text", "text": prompt},
                    {
                        "type": "input_image",
                        "image_url": f"data:image/png;base64,{encoded}",
                        "detail": "high",
                    },
                ],
            }
        ],
    )
    text = getattr(response, "output_text", "") or ""
    parsed = _parse_json_object(text)

    if not parsed:
        return {
            "key_colors": [],
            "recommended_page_variant": "classic-gold",
            "variant_reason": "Could not parse design recommendation.",
            "confidence": 0,
            "raw_response": text,
        }

    variant = parsed.get("recommended_page_variant")

    if variant not in PAGE_VARIANTS:
        parsed["recommended_page_variant"] = "classic-gold"
        parsed["variant_reason"] = f"Invalid variant {variant!r}; defaulted to classic-gold."
        parsed["confidence"] = 0

    return parsed


def _parse_json_object(text: str) -> dict[str, Any] | None:
    text = text.strip()

    if not text:
        return None

    if text.startswith("```"):
        text = text.removeprefix("```json").removeprefix("```").removesuffix("```").strip()

    try:
        value = json.loads(text)
    except json.JSONDecodeError:
        start = text.find("{")
        end = text.rfind("}")

        if start < 0 or end <= start:
            return None

        try:
            value = json.loads(text[start:end + 1])
        except json.JSONDecodeError:
            return None

    return value if isinstance(value, dict) else None


def _create_response_image(
    client: Any,
    row: dict[str, Any],
    full_prompt: str,
    options: ImageGenerationOptions,
    style_context: dict[str, Any],
) -> dict[str, Any]:
    response = client.responses.create(
        model=options.response_model,
        input=[
            {
                "role": "user",
                "content": [
                    {"type": "input_text", "text": full_prompt},
                    *[
                        {
                            "type": "input_image",
                            "file_id": reference["file_id"],
                            "detail": "high",
                        }
                        for reference in style_context["references"]
                    ],
                ],
            }
        ],
        tools=[
            {
                "type": "image_generation",
                "model": options.model,
                "size": options.size,
                "quality": options.quality,
                "background": options.background,
                "output_format": "png",
                "action": "generate",
            }
        ],
        tool_choice={"type": "image_generation"},
    )
    image_outputs = [
        output
        for output in response.output
        if getattr(output, "type", None) == "image_generation_call"
    ]

    if not image_outputs or not getattr(image_outputs[0], "result", None):
        raise RuntimeError(f"OpenAI returned no image data for {row['slug']}")

    image_output = image_outputs[0]

    return {
        "image_base64": image_output.result,
        "metadata": {
            "revised_prompt": getattr(image_output, "revised_prompt", None),
            "openai_created": getattr(response, "created_at", None) or getattr(response, "created", None),
            "response_id": getattr(response, "id", None),
            "image_generation_call_id": getattr(image_output, "id", None),
            "usage": _jsonable(getattr(response, "usage", None)),
        },
    }


def _image_api_metadata(result: Any) -> dict[str, Any]:
    image = result.data[0]

    return {
        "revised_prompt": getattr(image, "revised_prompt", None),
        "openai_created": getattr(result, "created", None),
        "background": getattr(result, "background", None),
        "response_id": None,
        "image_generation_call_id": None,
        "usage": _jsonable(getattr(result, "usage", None)),
    }


def _jsonable(value: Any) -> Any:
    if value is None:
        return None

    if hasattr(value, "model_dump"):
        return _jsonable(value.model_dump())

    if isinstance(value, (date, datetime, Decimal, UUID)):
        return str(value)

    if isinstance(value, list | tuple):
        return [_jsonable(item) for item in value]

    if isinstance(value, dict):
        return {str(key): _jsonable(item) for key, item in value.items()}

    return str(value)


def _ensure_api_key() -> None:
    if not os.environ.get("OPENAI_API_KEY"):
        raise RuntimeError("OPENAI_API_KEY must be set to generate images")


def _ensure_default_style_reference(paths: tuple[Path, ...]) -> None:
    if paths != DEFAULT_STYLE_REFERENCES:
        return

    if all(path.exists() for path in DEFAULT_STYLE_REFERENCES):
        return

    try:
        from PIL import Image
    except ImportError as exc:
        raise RuntimeError(
            "Pillow is required to create the small Ambry style reference. "
            "Install the pipeline dependencies with `pip install -e tools/pipeline`."
        ) from exc

    for source, target in zip(SOURCE_STYLE_REFERENCES, DEFAULT_STYLE_REFERENCES, strict=True):
        if not source.exists():
            raise FileNotFoundError(f"Style source image not found: {source}")

        if target.exists():
            continue

        target.parent.mkdir(parents=True, exist_ok=True)

        with Image.open(source) as image:
            image.thumbnail((240, 576), Image.Resampling.LANCZOS)
            image.save(target, "PNG", optimize=True)


def _existing_style_references(paths: tuple[Path, ...]) -> list[Path]:
    return [path for path in paths if path.exists()]


def _read_style_context(path: Path) -> dict[str, Any]:
    payload = json.loads(path.read_text(encoding="utf-8"))

    if not isinstance(payload, dict) or not isinstance(payload.get("references"), list):
        raise ValueError(f"Style context manifest is invalid: {path}")

    references = [
        reference
        for reference in payload["references"]
        if isinstance(reference, dict)
        and isinstance(reference.get("file_id"), str)
        and isinstance(reference.get("path"), str)
    ]

    if not references:
        raise ValueError(f"Style context manifest contains no file IDs: {path}")

    return {**payload, "references": references}


def _style_context_matches(path: Path, references: list[Path]) -> bool:
    try:
        manifest = _read_style_context(path)
    except (ValueError, json.JSONDecodeError):
        return False

    return [reference["path"] for reference in manifest["references"]] == [
        str(reference) for reference in references
    ]


def _image_path(output_dir: Path, slug: str) -> Path:
    return output_dir / slug / "original.png"


def _metadata_path(output_dir: Path, slug: str) -> Path:
    return output_dir / slug / "metadata.json"


def _portrait_path(saint_dir: Path) -> Path:
    return saint_dir / "portrait.webp"


def _thumb_path(saint_dir: Path) -> Path:
    return saint_dir / "thumb.webp"


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
