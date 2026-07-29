# Source Pipeline

This tool converts local source HTML into JSON. That is all it does right now.

Later stages can deconstruct those JSON documents into saints, facts, citations, and relationships. For now, keep this layer boring and lossless.

## New Advent HTML

From this directory:

```bash
PYTHONPATH=src python3 -m catholic_sources_pipeline new-advent-html ../../../newadvent build/raw/new-advent-cathen.json --subdir cathen --limit 25
```

Omit `--limit` to convert every HTML file in `../../../newadvent/cathen`:

```bash
PYTHONPATH=src python3 -m catholic_sources_pipeline new-advent-html ../../../newadvent build/raw/new-advent-cathen.json --subdir cathen
```

The output JSON has `source` metadata and a `documents` array. Each document includes `title`, `relative_path`, `text`, `raw_html`, and `metadata`.

## DB-Ready Values

After HTML is converted to JSON, prepare table-shaped values. Write them into a directory so each eventual database table can evolve independently:

```bash
PYTHONPATH=src python3 -m catholic_sources_pipeline db-ready build/raw/new-advent-cathen.json build/structured
```

This produces:

- `build/structured/manifest.json`
- `build/structured/sources.json`
- `build/structured/source-documents.json`
- `build/structured/citations.json`
- `build/structured/holy-people.json`

The holy people file is a conservative first pass from Catholic Encyclopedia article titles. Each row has a `type`, currently one of `saint`, `blessed`, `venerable`, or `pope`, so the app can filter these groups later without separate extraction paths.

These files keep provenance back to the source document and citation. They do not extract feast days, patronages, aliases, relationships, or detailed fact values yet.

## AI Enrichment

The AI enrichment step is intentionally separate from HTML conversion and DB-ready extraction. It requires `OPENAI_API_KEY` and should be run in small batches first.

Dry-run row selection:

```bash
PYTHONPATH=src python3 -m catholic_sources_pipeline ai-enrich build/structured/holy-people.json --dry-run --limit 10
```

Review candidate holy people and extract enriched metadata:

```bash
PYTHONPATH=src python3 -m catholic_sources_pipeline ai-enrich build/structured/holy-people.json --limit 10
```

The enrichment command batches candidates, defaulting to `--batch-size 10`, so the full current holy-people file is roughly 128 OpenAI requests instead of one request per row.

Outputs:

- `build/enriched/holy-people-reviews.json` contains one row per reviewed candidate with `type`, `virtues`, `vices`, `patronages`, `feast_days`, `roles`, `life_dates`, `image_prompt`, `confidence`, and `reason`.
- generated portraits are intentionally out of scope for this step and should live in a separate image pipeline later.

The review step does not send the full biography. It sends identifying fields plus a short source hint, then uses the OpenAI Responses API web search tool to validate whether the candidate is actually a holy person and to enrich metadata such as patronages.

## Image Generation

Generated saint portraits are local-first for files and app-DB-first for data. The command reads `image_prompt` values from the configured app database and writes one folder per saint. It skips existing `original.png` files unless `--force` is passed.

Each generated saint folder contains:

- `original.png` - the OpenAI output and local master
- `portrait.webp` - the primary app/display asset
- `thumb.webp` - the smaller search-result asset
- `metadata.json` - prompt, model, style context, design recommendation, and file metadata

By default, generation sends one smaller Ambry reference image to the OpenAI image edit endpoint as style context:

- `storage/app/generated/style-references/assisi-final-small.png`

The smaller reference is created from the selected St. Francis of Assisi portrait on demand. It is used for collection style only: icon-like devotional illustration, geometric robe folds, crisp contour lines, large halos, and clean negative space around the figure. Pass `--no-style-references` to use text-only generation, or pass one or more `--style-reference path/to/image.png` values to override the default.

For the full batch, avoid sending those PNG bytes on every request. Upload them once to OpenAI's Files API and save a local manifest:

```bash
OPENAI_API_KEY=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline prepare-image-style-context
```

That writes `storage/app/generated/openai-style-context.json` with reusable `file_id` values. Then generate with the Responses API image generation tool:

```bash
OPENAI_API_KEY=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline generate-images storage/app/generated/saints --style-context storage/app/generated/openai-style-context.json --limit 1
```

This still includes the references as visual context for each generation, so they may still count as image input tokens, but it avoids repeatedly uploading the same PNG files.

Dry-run the first few candidates from the repository root:

```bash
PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline generate-images storage/app/generated/saints --dry-run --limit 5
```

Generate one trial image:

```bash
OPENAI_API_KEY=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline generate-images storage/app/generated/saints --style-context storage/app/generated/openai-style-context.json --limit 1
```

After each image generation, the pipeline asks the model to inspect the generated portrait and store:

- `key_colors`
- `recommended_page_variant`
- `variant_reason`
- `confidence`

The recommendation chooses from Ambry's 17 page variants: `classic-gold`, `celtic-green`, `marian-blue`, `martyr-crimson`, `monastic-olive`, `desert-rose`, `bishop-plum`, `doctor-indigo`, `virgin-ivory`, `mission-teal`, `papal-cream`, `ascetic-stone`, `dominican-charcoal`, `royal-red-gold`, `byzantine-jewel`, `floral-rose`, and `sea-aqua`. Pass `--design-analysis none` to skip this extra model call.

The default image model is `gpt-image-2`, with `800x1008` PNG output and `high` quality. GPT Image 2 requires image dimensions to be divisible by 16, so this keeps the master near 1000px tall with a wider full-body figure while keeping the app-facing `portrait.webp` derivative at `1200px` tall with automatic width. GPT Image 2 does not support native transparent backgrounds, so generated masters use a plain removable light background and should go through a later background-removal step before final upload. The default WebP settings are `--portrait-webp-quality 86`, `--thumb-height 474`, and `--thumb-webp-quality 80`. To generate the full set, pass `--all` explicitly:

```bash
OPENAI_API_KEY=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline generate-images storage/app/generated/saints --style-context storage/app/generated/openai-style-context.json --all
```

Start with saints that have patronage links:

```bash
OPENAI_API_KEY=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline generate-images storage/app/generated/saints --style-context storage/app/generated/openai-style-context.json --has-patronages --canonical-status saint --limit 10
```

## Background Removal

Background removal is a separate post-processing stage. It reads generated saint folders from `storage/app/generated/saints` and writes transparent cutouts to a separate tree, so the OpenAI masters stay untouched:

```bash
PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline remove-image-backgrounds storage/app/generated/saints storage/app/generated/background-removed/saints --slug st-teresa-of-avila --force
```

Each output saint folder contains:

- `cutout.png` - transparent PNG master
- `portrait.webp` - transparent primary app/display asset
- `thumb.webp` - transparent search-result asset
- `metadata.json` - source image, remover settings, source generation summary, and file metadata

The default `light-bg` provider is dependency-light and works best with the plain removable backgrounds requested from GPT Image 2. It removes only near-background pixels connected to the image edges, which helps preserve white veils, pages, halos, and robe details inside the saint. For difficult images, install the optional ML remover and switch providers:

Derivative resizing is height-based: `portrait.webp` defaults to `1200px` tall and `thumb.webp` defaults to `474px` tall, with width calculated from the source aspect ratio. Background removal also trims transparent empty space tightly from the left and right while preserving the full image height. Tune with `--trim-padding-ratio` and `--trim-min-width-ratio`, or pass `--no-horizontal-trim` to keep the original width.

The `light-bg` provider defaults are intentionally conservative around the figure while still allowing a broader background fade. If a generated background survives, increase `--transition` slightly for that saint.

```bash
pip install -e 'tools/pipeline[background-removal]'
PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline remove-image-backgrounds storage/app/generated/saints storage/app/generated/background-removed/saints --provider rembg --slug st-teresa-of-avila --force
```

Dry-run a batch first:

```bash
PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline remove-image-backgrounds storage/app/generated/saints storage/app/generated/background-removed/saints --dry-run --limit 10
```

Process every generated saint explicitly with `--all`.

### Upload transparent portraits to Vercel Blob

After background removal, upload the transparent assets to public Vercel Blob storage and write the public URLs back to the app database:

```bash
BLOB_READ_WRITE_TOKEN=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline upload-saint-blobs storage/app/generated/background-removed/saints --all
```

This uploads `cutout.png`, `portrait.webp`, and `thumb.webp` under stable public paths like `saints/v1/st-francis-of-assisi/portrait.webp`. Without `--slug`, the command selects saints that have complete transparent asset folders locally. Re-running the command overwrites the same Blob paths and updates `image_cutout_url`, `image_portrait_url`, and `image_thumb_url`.

Use `--missing-only` to upload only complete local transparent asset folders whose DB URL columns have not been filled yet:

```bash
BLOB_READ_WRITE_TOKEN=... PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline upload-saint-blobs storage/app/generated/background-removed/saints --all --missing-only
```

### Interactive image console

Run the image console to preview/generate the next saint batch, remove backgrounds for every local generated original, upload missing processed assets, and write image URLs into the app database:

```bash
PYTHONPATH=tools/pipeline/src python3 -m catholic_sources_pipeline image-console
```

The full-pipeline option generates the next 10 saint rows with patronages, processes all local originals missing transparent assets, and uploads complete processed folders missing Blob URLs directly into the app database.

Keep generated files out of Git. Later, this local directory can become the source for a Blob/object-storage upload step.

### Legacy SQLite to Neon/Postgres Import

Set the Neon pooled or direct connection string locally:

```bash
DB_CONNECTION=pgsql
DB_URL=postgresql://user:password@host.neon.tech/database?sslmode=require
```

Preview what will be copied:

```bash
php artisan db:copy-sqlite-to-pgsql --dry-run
```

Create the Postgres schema with Laravel migrations and copy the local SQLite rows:

```bash
php artisan db:copy-sqlite-to-pgsql --fresh
```

The copy command skips the SQLite `migrations` table, runs migrations on the configured `pgsql` connection, copies app tables in foreign-key-safe order, and resets Postgres integer sequences after explicit ID inserts.
