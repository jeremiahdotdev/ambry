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
