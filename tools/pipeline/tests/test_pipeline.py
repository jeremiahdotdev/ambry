from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from catholic_sources_pipeline.background_removal import BackgroundRemovalOptions, run_background_removal
from catholic_sources_pipeline.blob_upload import BlobUploadOptions, run_blob_upload
from catholic_sources_pipeline.db_ready import build_db_ready_payload, write_db_ready_payload
from catholic_sources_pipeline.images import (
    ImageGenerationOptions,
    StyleContextOptions,
    build_portrait_prompt,
    PAGE_VARIANTS,
    prepare_style_context,
    run_image_generation,
)
from catholic_sources_pipeline.load_sqlite import load_db_ready_json, load_saints_json
from catholic_sources_pipeline.new_advent import build_new_advent_payload


class PipelineSmokeTest(unittest.TestCase):
    def test_blob_upload_dry_run_adds_urls_for_complete_transparent_assets(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"
            saint_dir = workspace / "cutouts" / "st-sample"
            saint_dir.mkdir(parents=True)
            (saint_dir / "cutout.png").write_bytes(b"png")
            (saint_dir / "portrait.webp").write_bytes(b"webp")
            (saint_dir / "thumb.webp").write_bytes(b"webp")

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table saints (
                    id text primary key,
                    slug text not null
                );
                insert into saints (id, slug) values ('1', 'st-sample');
                """
            )
            connection.close()

            counts = run_blob_upload(
                BlobUploadOptions(
                    database_path=db_path,
                    input_dir=workspace / "cutouts",
                    dry_run=True,
                    limit=10,
                )
            )

        self.assertEqual(1, counts["selected"])
        self.assertEqual(3, counts["uploaded_files"])
        self.assertEqual(1, counts["updated_rows"])
        self.assertEqual(0, counts["missing_assets"])

    def test_background_removal_creates_transparent_cutout_derivatives_and_metadata(self) -> None:
        from PIL import Image, ImageDraw

        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            input_dir = workspace / "saints"
            source_dir = input_dir / "st-sample"
            output_dir = workspace / "cutouts"
            source_dir.mkdir(parents=True)

            image = Image.new("RGB", (80, 120), (248, 248, 244))
            draw = ImageDraw.Draw(image)
            draw.rectangle((24, 24, 56, 100), fill=(150, 40, 40))
            image.save(source_dir / "original.png")
            (source_dir / "metadata.json").write_text(
                json.dumps({"model": "test-model", "size": "80x120", "generated_at": "now"}),
                encoding="utf-8",
            )

            counts = run_background_removal(
                BackgroundRemovalOptions(
                    input_dir=input_dir,
                    output_dir=output_dir,
                    portrait_size="80x120",
                    thumb_height=30,
                    tolerance=8,
                    transition=30,
                    feather_radius=0,
                )
            )

            cutout_path = output_dir / "st-sample" / "cutout.png"
            metadata = json.loads((output_dir / "st-sample" / "metadata.json").read_text(encoding="utf-8"))

            with Image.open(cutout_path) as cutout:
                self.assertEqual((35, 120), cutout.size)
                self.assertEqual(0, cutout.getpixel((0, 0))[3])
                self.assertEqual(255, cutout.getpixel((17, 60))[3])

            with Image.open(output_dir / "st-sample" / "portrait.webp") as portrait:
                self.assertEqual((35, 120), portrait.size)

            with Image.open(output_dir / "st-sample" / "thumb.webp") as thumb:
                self.assertEqual((9, 30), thumb.size)

            self.assertEqual(1, counts["selected"])
            self.assertEqual(1, counts["processed"])
            self.assertTrue((output_dir / "st-sample" / "portrait.webp").exists())
            self.assertTrue((output_dir / "st-sample" / "thumb.webp").exists())
            self.assertEqual("light-bg", metadata["provider"])
            self.assertTrue(metadata["trim"]["horizontal"])

    def test_background_removal_preserves_internal_background_colored_details(self) -> None:
        from PIL import Image, ImageDraw

        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            source_dir = workspace / "saints" / "st-sample"
            output_dir = workspace / "cutouts"
            source_dir.mkdir(parents=True)

            image = Image.new("RGB", (90, 120), (248, 248, 244))
            draw = ImageDraw.Draw(image)
            draw.rectangle((18, 20, 72, 110), fill=(130, 60, 50))
            draw.rectangle((38, 40, 52, 58), fill=(248, 248, 244))
            image.save(source_dir / "original.png")

            run_background_removal(
                BackgroundRemovalOptions(
                    input_dir=workspace / "saints",
                    output_dir=output_dir,
                    portrait_size="90x120",
                    thumb_height=30,
                    tolerance=10,
                    transition=52,
                    feather_radius=0,
                )
            )

            with Image.open(output_dir / "st-sample" / "cutout.png") as cutout:
                self.assertEqual(0, cutout.getpixel((0, 0))[3])
                self.assertEqual(255, cutout.getpixel((27, 49))[3])

    def test_background_removal_can_keep_full_width(self) -> None:
        from PIL import Image, ImageDraw

        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            source_dir = workspace / "saints" / "st-sample"
            output_dir = workspace / "cutouts"
            source_dir.mkdir(parents=True)

            image = Image.new("RGB", (80, 120), (248, 248, 244))
            draw = ImageDraw.Draw(image)
            draw.rectangle((24, 24, 56, 100), fill=(150, 40, 40))
            image.save(source_dir / "original.png")

            run_background_removal(
                BackgroundRemovalOptions(
                    input_dir=workspace / "saints",
                    output_dir=output_dir,
                    portrait_size="80x120",
                    thumb_height=30,
                    tolerance=8,
                    transition=30,
                    feather_radius=0,
                    trim_horizontal=False,
                )
            )

            with Image.open(output_dir / "st-sample" / "cutout.png") as cutout:
                self.assertEqual((80, 120), cutout.size)

    def test_background_removal_dry_run_skips_existing_cutouts(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            source_dir = workspace / "saints" / "st-sample"
            cutout_dir = workspace / "cutouts" / "st-sample"
            source_dir.mkdir(parents=True)
            cutout_dir.mkdir(parents=True)
            (source_dir / "original.png").write_bytes(b"png")
            (cutout_dir / "cutout.png").write_bytes(b"png")

            counts = run_background_removal(
                BackgroundRemovalOptions(
                    input_dir=workspace / "saints",
                    output_dir=workspace / "cutouts",
                    dry_run=True,
                )
            )

        self.assertEqual(1, counts["selected"])
        self.assertEqual(1, counts["skipped"])
        self.assertEqual(0, counts["processed"])

    def test_style_context_dry_run_selects_reference_images(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            reference = workspace / "default.png"
            reference.write_bytes(b"png")

            counts = prepare_style_context(
                StyleContextOptions(
                    output_path=workspace / "style-context.json",
                    style_references=(reference,),
                    dry_run=True,
                )
            )

        self.assertEqual(1, counts["references"])
        self.assertEqual(0, counts["uploaded"])

    def test_image_generation_dry_run_selects_prompted_saints(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"
            output_dir = workspace / "images"

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table saints (
                    id text primary key,
                    primary_name text not null,
                    slug text not null,
                    life_dates text,
                    gender text,
                    canonical_status text,
                    image_prompt text
                );
                insert into saints (
                    id, primary_name, slug, life_dates, gender, canonical_status, image_prompt
                ) values
                    ('1', 'St. Agnes of Rome', 'st-agnes-of-rome', 'c. 291-304 AD', 'female', 'saint', 'Young Roman martyr holding a palm branch.'),
                    ('2', 'St. No Prompt', 'st-no-prompt', null, null, 'saint', null);
                """
            )
            connection.close()

            counts = run_image_generation(
                ImageGenerationOptions(
                    database_path=db_path,
                    output_dir=output_dir,
                    dry_run=True,
                    limit=10,
                )
            )

        self.assertEqual(1, counts["selected"])
        self.assertEqual(0, counts["skipped"])
        self.assertEqual(0, counts["generated"])

    def test_image_generation_dry_run_skips_existing_pngs(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"
            output_dir = workspace / "images"
            saint_dir = output_dir / "st-agnes-of-rome"
            saint_dir.mkdir(parents=True)
            (saint_dir / "original.png").write_bytes(b"png")

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table saints (
                    id text primary key,
                    primary_name text not null,
                    slug text not null,
                    life_dates text,
                    gender text,
                    canonical_status text,
                    image_prompt text
                );
                insert into saints (
                    id, primary_name, slug, life_dates, gender, canonical_status, image_prompt
                ) values
                    ('1', 'St. Agnes of Rome', 'st-agnes-of-rome', null, 'female', 'saint', 'Young Roman martyr.');
                """
            )
            connection.close()

            counts = run_image_generation(
                ImageGenerationOptions(
                    database_path=db_path,
                    output_dir=output_dir,
                    dry_run=True,
                    limit=10,
                )
            )

        self.assertEqual(1, counts["selected"])
        self.assertEqual(1, counts["skipped"])
        self.assertEqual(0, counts["generated"])

    def test_image_generation_dry_run_selects_slug(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table saints (
                    id text primary key,
                    primary_name text not null,
                    slug text not null,
                    life_dates text,
                    gender text,
                    canonical_status text,
                    image_prompt text
                );
                insert into saints (
                    id, primary_name, slug, life_dates, gender, canonical_status, image_prompt
                ) values
                    ('1', 'St. Agnes of Rome', 'st-agnes-of-rome', null, 'female', 'saint', 'Young Roman martyr.'),
                    ('2', 'St. Francis of Assisi', 'st-francis-of-assisi', null, 'male', 'saint', 'Franciscan friar.');
                """
            )
            connection.close()

            counts = run_image_generation(
                ImageGenerationOptions(
                    database_path=db_path,
                    output_dir=workspace / "images",
                    dry_run=True,
                    slug="st-francis-of-assisi",
                )
            )

        self.assertEqual(1, counts["selected"])
        self.assertEqual(0, counts["generated"])

    def test_image_prompt_adds_collection_style_constraints(self) -> None:
        prompt = build_portrait_prompt(
            {
                "primary_name": "St. Agnes of Rome",
                "canonical_status": "saint",
                "life_dates": "c. 291-304 AD",
                "gender": "female",
                "image_prompt": "Young Roman martyr holding a palm branch.",
            },
            "832x1216",
        )

        self.assertIn("St. Agnes of Rome", prompt)
        self.assertIn("exactly 832 pixels wide by 1216 pixels tall", prompt)
        self.assertIn("Do not return a square image", prompt)
        self.assertIn("Do not include text", prompt)
        self.assertIn("attached Ambry reference images", prompt)
        self.assertIn("Do not copy the face", prompt)
        self.assertIn("clean geometric robe folds", prompt)
        self.assertIn("known historical or traditional iconographic traits", prompt)
        self.assertIn("Avoid generic repeated faces", prompt)
        self.assertIn("full-body standing portrait", prompt)
        self.assertIn("feet visible", prompt)
        self.assertIn("normal human proportions", prompt)
        self.assertIn("Do not elongate", prompt)
        self.assertIn("plain very light removable background", prompt)
        self.assertIn("Do not place animals", prompt)
        self.assertIn("bottom half", prompt)
        self.assertIn("map to one Ambry page variant", prompt)

    def test_page_variant_catalog_has_persistable_variants(self) -> None:
        self.assertEqual(17, len(PAGE_VARIANTS))
        self.assertIn("classic-gold", PAGE_VARIANTS)
        self.assertIn("martyr-crimson", PAGE_VARIANTS)
        self.assertIn("dominican-charcoal", PAGE_VARIANTS)
        self.assertIn("royal-red-gold", PAGE_VARIANTS)
        self.assertIn("byzantine-jewel", PAGE_VARIANTS)
        self.assertIn("floral-rose", PAGE_VARIANTS)
        self.assertIn("sea-aqua", PAGE_VARIANTS)

    def test_new_advent_reader_converts_html_to_json_documents(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            cathen = workspace / "cathen"
            cathen.mkdir()
            (cathen / "01214a.htm").write_text(
                """
                <html>
                  <head><title>CATHOLIC ENCYCLOPEDIA: Source Article</title></head>
                  <body>
                    <div id="springfield2">
                      <h1>Source Article</h1>
                      <p>Article body.</p>
                      <div class="pub">
                        <p id="apa"><span id="apaauthor">Author Name.</span>
                        <span id="apaarticle">Source Article.</span>
                        <span id="apaurl">http://www.newadvent.org/cathen/01214a.htm</span></p>
                      </div>
                    </div>
                  </body>
                </html>
                """,
                encoding="utf-8",
            )

            payload = build_new_advent_payload(workspace)

        self.assertIn("source", payload)
        self.assertIn("documents", payload)
        self.assertIn("title", payload["documents"][0])
        self.assertIn("text", payload["documents"][0])
        self.assertIn("raw_html", payload["documents"][0])
        self.assertIn("relative_path", payload["documents"][0])

    def test_db_ready_layer_outputs_source_tables(self) -> None:
        payload = build_db_ready_payload(
            {
                "documents": [
                    {
                        "title": "St. Source Article",
                        "relative_path": "cathen/01214a.htm",
                        "text": "Article body. Martyr and Doctor of the Church.",
                        "raw_html": "<html>Article body.</html>",
                        "metadata": {"citation": {"apaauthor": "Author Name."}},
                    },
                    {
                        "title": "Blessed Sample Person",
                        "relative_path": "cathen/99999a.htm",
                        "text": "Article body.",
                        "raw_html": "<html>Article body.</html>",
                        "metadata": {"citation": {"apaauthor": "Author Name."}},
                    }
                ]
            }
        )

        self.assertIn("tables", payload)
        self.assertIn("sources", payload["tables"])
        self.assertIn("source_documents", payload["tables"])
        self.assertIn("citations", payload["tables"])
        self.assertIn("holy_people", payload["tables"])
        self.assertNotIn("saints", payload["tables"])
        self.assertEqual(2, payload["counts"]["source_documents"])
        self.assertEqual(2, payload["counts"]["holy_people"])
        self.assertEqual("saint", payload["tables"]["holy_people"][0]["type"])
        self.assertEqual("blessed", payload["tables"]["holy_people"][1]["type"])
        self.assertEqual("St. Source Article", payload["tables"]["holy_people"][0]["primary_name"])
        self.assertTrue(payload["tables"]["holy_people"][0]["is_martyr"])
        self.assertTrue(payload["tables"]["holy_people"][0]["is_doctor"])

    def test_db_ready_layer_extracts_life_dates_into_holy_people(self) -> None:
        payload = build_db_ready_payload(
            {
                "documents": [
                    {
                        "title": "Bl. Agnellus of Pisa",
                        "relative_path": "cathen/01234a.htm",
                        "text": "Bl. Agnellus of Pisa Friar Minor, born at Pisa c. 1195; died at Oxford, 7 May, 1236.",
                        "raw_html": "<html></html>",
                        "metadata": {"citation": {"apaauthor": "Author Name."}},
                    },
                    {
                        "title": "St. Adalard",
                        "relative_path": "cathen/01234b.htm",
                        "text": "St. Adalard Born c. 751; d. 2 January, 827.",
                        "raw_html": "<html></html>",
                        "metadata": {"citation": {"apaauthor": "Author Name."}},
                    },
                    {
                        "title": "St. Adelaide",
                        "relative_path": "cathen/01234c.htm",
                        "text": "St. Adelaide Abbess, born in the tenth century; died at Cologne, 5 February, 1015.",
                        "raw_html": "<html></html>",
                        "metadata": {"citation": {"apaauthor": "Author Name."}},
                    },
                ]
            }
        )

        agnellus, adalard, adelaide = payload["tables"]["holy_people"]

        self.assertEqual("blessed", agnellus["type"])
        self.assertEqual(1195, agnellus["birth_year"])
        self.assertEqual("circa", agnellus["birth_year_qualifier"])
        self.assertEqual(1236, agnellus["death_year"])
        self.assertEqual("exact", agnellus["death_year_qualifier"])
        self.assertEqual("c. 1195 - 1236", agnellus["life_dates"])
        self.assertEqual(5, agnellus["metadata"]["death"]["month"])
        self.assertEqual(7, agnellus["metadata"]["death"]["day"])
        self.assertEqual("circa", agnellus["metadata"]["birth"]["certainty"])

        self.assertEqual(751, adalard["birth_year"])
        self.assertEqual("exact", adalard["birth_year_qualifier"])
        self.assertEqual(827, adalard["death_year"])
        self.assertEqual("exact", adalard["death_year_qualifier"])
        self.assertEqual(1, adalard["metadata"]["death"]["month"])
        self.assertEqual(2, adalard["metadata"]["death"]["day"])

        self.assertEqual(950, adelaide["birth_year"])
        self.assertEqual("century", adelaide["birth_year_qualifier"])
        self.assertEqual("century", adelaide["metadata"]["birth"]["certainty"])
        self.assertEqual(1015, adelaide["death_year"])
        self.assertEqual("10th century - 1015", adelaide["life_dates"])

    def test_db_ready_layer_writes_split_json_files(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            input_path = workspace / "new-advent.json"
            output_dir = workspace / "db-ready"

            input_path.write_text(
                json.dumps(
                    {
                        "documents": [
                            {
                                "title": "St. Agnes of Rome.",
                                "relative_path": "cathen/01214a.htm",
                                "text": "Virgin martyr of Rome.",
                                "raw_html": "<html>Virgin martyr of Rome.</html>",
                                "metadata": {"citation": {"apaauthor": "Author Name."}},
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )

            write_db_ready_payload(input_path, output_dir)

            holy_people = json.loads((output_dir / "holy-people.json").read_text(encoding="utf-8"))
            manifest = json.loads((output_dir / "manifest.json").read_text(encoding="utf-8"))

        self.assertEqual("holy_people", holy_people["table"])
        self.assertEqual(1, holy_people["count"])
        self.assertEqual("saint", holy_people["rows"][0]["type"])
        self.assertEqual("St. Agnes of Rome", holy_people["rows"][0]["primary_name"])
        self.assertEqual(1, manifest["tables"]["holy_people"]["count"])
        self.assertNotIn("saints", manifest["tables"])

    def test_sqlite_loader_loads_source_tables(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"
            input_path = workspace / "db-ready.json"

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table sources (
                    id text primary key,
                    name text not null,
                    slug text not null unique,
                    type text,
                    license text,
                    attribution text,
                    canonical_url text,
                    reliability_notes text,
                    created_at text,
                    updated_at text
                );
                create table source_documents (
                    id text primary key,
                    source_id text not null,
                    title text not null,
                    slug text,
                    author text,
                    edition text,
                    language text,
                    url text,
                    raw_text text,
                    checksum text,
                    metadata text,
                    created_at text,
                    updated_at text
                );
                create table citations (
                    id text primary key,
                    source_id text,
                    title text,
                    locator text,
                    url text,
                    excerpt text,
                    accessed_at text,
                    created_at text,
                    updated_at text
                );
                """
            )
            connection.close()

            payload = build_db_ready_payload(
                {
                    "documents": [
                        {
                            "title": "Source Article",
                            "relative_path": "cathen/01214a.htm",
                            "text": "Article body.",
                            "raw_html": "<html>Article body.</html>",
                            "metadata": {"citation": {"apaauthor": "Author Name."}},
                        }
                    ]
                }
            )
            input_path.write_text(json.dumps(payload), encoding="utf-8")

            counts = load_db_ready_json(input_path, db_path)

        self.assertEqual(1, counts["source_documents"])

    def test_sqlite_loader_loads_saints_table(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            workspace = Path(tmpdir)
            db_path = workspace / "database.sqlite"
            input_path = workspace / "holy-people.json"

            connection = __import__("sqlite3").connect(db_path)
            connection.executescript(
                """
                create table saints (
                    id text primary key,
                    primary_name text not null,
                    slug text not null unique,
                    biography text,
                    birth_year integer,
                    birth_year_qualifier text,
                    death_year integer,
                    death_year_qualifier text,
                    life_dates text,
                    gender text,
                    canonical_status text not null default 'saint',
                    is_martyr integer not null default 0,
                    is_doctor integer not null default 0,
                    created_at text,
                    updated_at text
                );
                """
            )
            connection.close()

            input_path.write_text(
                json.dumps(
                    {
                        "table": "holy_people",
                        "rows": [
                            {
                                "id": "95c7758f-b72c-55c6-a2b4-d0e526174d8a",
                                "type": "saint",
                                "primary_name": "St. Agnes of Rome",
                                "slug": "st-agnes-of-rome",
                                "biography": "Virgin martyr of Rome.",
                                "birth_year": None,
                                "birth_year_qualifier": "unknown",
                                "death_year": 304,
                                "death_year_qualifier": "exact",
                                "life_dates": "304",
                                "gender": "female",
                                "canonical_status": "saint",
                                "is_martyr": True,
                                "is_doctor": False,
                            }
                        ],
                    }
                ),
                encoding="utf-8",
            )

            counts = load_saints_json(input_path, db_path)

            with __import__("sqlite3").connect(db_path) as connection:
                row = connection.execute("select primary_name, death_year, is_martyr from saints").fetchone()

        self.assertEqual(1, counts["saints"])
        self.assertEqual(("St. Agnes of Rome", 304, 1), row)


if __name__ == "__main__":
    unittest.main()
