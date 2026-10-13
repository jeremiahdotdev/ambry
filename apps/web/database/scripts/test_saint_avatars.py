import io
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import patch

from PIL import Image

import backfill_saint_avatars as avatars


class AvatarTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'public/saints').mkdir(parents=True)
        source = Image.new('RGBA', (120, 200), (255, 0, 0, 0))
        for y in range(110):
            for x in range(120):
                source.putpixel((x, y), (0, y, x, 255))
        source.save(self.root / 'public/saints/st-test.png')
        source.save(self.root / 'public/saints/default.png')
        self.original = (self.root / 'public/saints/st-test.png').read_bytes()
        self.db = sqlite3.connect(':memory:', isolation_level=None)
        self.db.row_factory = sqlite3.Row
        self.addCleanup(self.db.close)
        self.db.execute('CREATE TABLE saints (id TEXT PRIMARY KEY, slug TEXT, '
                        'image_thumb_url TEXT, image_avatar_url TEXT, '
                        'image_portrait_url TEXT, image_cutout_url TEXT, updated_at TEXT)')
        self.db.execute("INSERT INTO saints VALUES ('123', 'st-test', NULL, NULL, 'portrait', 'cutout', 'unchanged')")

    def run_backfill(self, **kwargs):
        return avatars.backfill(self.db, self.root, None, **kwargs)

    def test_crop_matches_css_full_width_top_55_percent_and_alpha(self):
        result = Image.open(io.BytesIO(avatars.crop_avatar(self.original)))
        self.assertEqual(result.size, (120, 110))
        self.assertEqual(result.getpixel((0, 0)), (0, 0, 0, 255))
        self.assertEqual(result.getpixel((119, 109)), (0, 109, 119, 255))
        transparent = Image.new('RGBA', (100, 200), (0, 0, 0, 0))
        data = io.BytesIO()
        transparent.save(data, 'PNG')
        result = Image.open(io.BytesIO(avatars.crop_avatar(data.getvalue())))
        self.assertEqual(result.getpixel((0, 0))[3], 0)

    def test_dry_run_never_uploads_or_changes_database(self):
        before = tuple(self.db.execute('SELECT * FROM saints').fetchone())
        with patch.object(avatars, 'upload_avatar') as upload:
            stats = self.run_backfill()
        upload.assert_not_called()
        self.assertEqual(stats['prepared'], 1)
        self.assertEqual(tuple(self.db.execute('SELECT * FROM saints').fetchone()), before)

    def test_apply_changes_only_avatar_and_rerun_skips(self):
        before = dict(self.db.execute('SELECT * FROM saints').fetchone())
        with patch.object(avatars, 'upload_avatar', return_value='https://store.public.blob.vercel-storage.com/avatar.png') as upload:
            stats = self.run_backfill(apply=True, token='test')
            self.assertEqual(stats['saved'], 1)
            self.assertEqual(self.run_backfill(apply=True, token='test')['selected'], 0)
            upload.assert_called_once()
        after = dict(self.db.execute('SELECT * FROM saints').fetchone())
        self.assertTrue(after.pop('image_avatar_url').endswith('/avatar.png'))
        before.pop('image_avatar_url')
        self.assertEqual(after, before)
        self.assertEqual((self.root / 'public/saints/st-test.png').read_bytes(), self.original)

    def test_thumbnail_is_source_instead_of_portrait(self):
        self.db.execute("UPDATE saints SET image_thumb_url = 'https://example.com/thumb.webp'")
        with patch.object(avatars, 'read_source', return_value=self.original) as read:
            self.run_backfill()
        read.assert_called_once_with('https://example.com/thumb.webp', None)

    def test_concurrent_image_edit_is_not_overwritten(self):
        def upload(*args):
            self.db.execute("UPDATE saints SET image_thumb_url = 'new-thumb'")
            return 'https://store.public.blob.vercel-storage.com/avatar.png'
        with patch.object(avatars, 'upload_avatar', side_effect=upload):
            stats = self.run_backfill(apply=True, token='test')
        self.assertEqual(stats['conflicts'], 1)
        self.assertIsNone(self.db.execute('SELECT image_avatar_url FROM saints').fetchone()[0])

    def test_failed_record_does_not_prevent_later_saints(self):
        self.db.execute("INSERT INTO saints VALUES ('456', 'st-other', NULL, NULL, 'portrait', 'cutout', 'unchanged')")
        with patch.object(avatars, 'upload_avatar', side_effect=[ValueError('upload failed'), 'https://store.public.blob.vercel-storage.com/avatar.png']):
            stats = self.run_backfill(apply=True, token='test')
        self.assertEqual((stats['failed'], stats['saved']), (1, 1))
        self.assertIsNone(self.db.execute("SELECT image_avatar_url FROM saints WHERE id='123'").fetchone()[0])


if __name__ == '__main__':
    unittest.main()
