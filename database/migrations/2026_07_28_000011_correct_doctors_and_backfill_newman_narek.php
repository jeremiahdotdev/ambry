<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->upsertSaint([
            'id' => '2511f6b9-78bb-5542-af12-e7734e26822e',
            'primary_name' => 'St. John Henry Newman',
            'slug' => 'st-john-henry-newman',
            'biography' => $this->documentText('cathen-10794a') ?: 'English theologian, cardinal, convert, and Doctor of the Church.',
            'birth_year' => 1801,
            'birth_year_qualifier' => 'exact',
            'death_year' => 1890,
            'death_year_qualifier' => 'exact',
            'life_dates' => '1801-1890 AD',
            'gender' => 'male',
            'canonical_status' => 'saint',
            'is_martyr' => false,
            'is_doctor' => true,
            'virtues' => $this->json(['faith', 'learning', 'conscience', 'perseverance']),
            'vices' => $this->json([]),
            'roles' => $this->json(['priest', 'cardinal', 'theologian', 'convert', 'doctor of the church']),
            'ai_reason' => 'Backfilled from New Advent Catholic Encyclopedia Newman article; doctor status current as of 2025.',
            'ai_confidence' => 0.92,
            'image_prompt' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sources')->updateOrInsert(
            ['id' => '53414aaf-7ff7-559a-8f9e-190dc0b80a4a'],
            [
                'name' => 'United States Conference of Catholic Bishops',
                'slug' => 'usccb',
                'type' => 'catholic_bishops_conference',
                'license' => null,
                'attribution' => 'United States Conference of Catholic Bishops',
                'canonical_url' => 'https://www.usccb.org/',
                'reliability_notes' => 'Used for contemporary saint and liturgical calendar backfills not present in the 1913 Catholic Encyclopedia.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $gregoryText = 'Saint Gregory of Narek was an Armenian monk, priest, poet, mystic, theologian, and Doctor of the Church. Born about 950 near Lake Van, he entered Narek Monastery, became known for mystical theology and devotion to Mary, and is remembered especially for the Book of Lamentations. His memorial is February 27.';

        DB::table('source_documents')->updateOrInsert(
            ['id' => 'c8ed4824-9b9e-5aa2-a10a-b03b6ece5f84'],
            [
                'source_id' => '53414aaf-7ff7-559a-8f9e-190dc0b80a4a',
                'title' => 'Saint Gregory of Narek',
                'slug' => 'usccb-saint-gregory-of-narek',
                'author' => null,
                'edition' => 'USCCB web article',
                'language' => 'en',
                'url' => 'https://www.usccb.org/prayer-worship/liturgical-year/saint-gregory-of-narek',
                'raw_text' => $gregoryText,
                'checksum' => hash('sha256', $gregoryText),
                'metadata' => $this->json(['source' => 'usccb']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('citations')->updateOrInsert(
            ['id' => '1278e2bd-4ef9-53a3-8902-641ecac7fde5'],
            [
                'source_id' => '53414aaf-7ff7-559a-8f9e-190dc0b80a4a',
                'title' => 'Saint Gregory of Narek',
                'locator' => 'USCCB Saint Gregory of Narek',
                'url' => 'https://www.usccb.org/prayer-worship/liturgical-year/saint-gregory-of-narek',
                'excerpt' => 'Saint Gregory of Narek, Armenian monk and Doctor of the Church.',
                'accessed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->upsertSaint([
            'id' => '5de180db-b6f1-5a0b-9470-9b90f8ad81fb',
            'primary_name' => 'St. Gregory of Narek',
            'slug' => 'st-gregory-of-narek',
            'biography' => $gregoryText,
            'birth_year' => 950,
            'birth_year_qualifier' => 'circa',
            'death_year' => 1005,
            'death_year_qualifier' => 'circa',
            'life_dates' => 'c. 950-c. 1005 AD',
            'gender' => 'male',
            'canonical_status' => 'saint',
            'is_martyr' => false,
            'is_doctor' => true,
            'virtues' => $this->json(['prayer', 'learning', 'devotion', 'wisdom']),
            'vices' => $this->json([]),
            'roles' => $this->json(['monk', 'priest', 'poet', 'mystic', 'theologian', 'doctor of the church']),
            'ai_reason' => 'Backfilled from USCCB Saint Gregory of Narek article.',
            'ai_confidence' => 0.92,
            'image_prompt' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('saints')->update(['is_doctor' => false]);

        DB::table('saints')
            ->whereIn('slug', [
                'st-ambrose',
                'st-augustine-of-hippo',
                'st-jerome',
                'pope-st-gregory-i-the-great',
                'st-john-chrysostom',
                'st-basil-the-great',
                'st-gregory-of-nazianzus',
                'st-athanasius',
                'st-ephraem',
                'st-hilary-of-poitiers',
                'st-cyril-of-jerusalem',
                'st-cyril-of-alexandria',
                'st-john-damascene',
                'st-bede-the-venerable',
                'st-peter-damian',
                'st-anselm-cathen-01546a',
                'st-bernard-of-clairvaux',
                'st-anthony-of-padua',
                'st-albertus-magnus',
                'st-bonaventure',
                'st-thomas-aquinas',
                'st-catherine-of-siena',
                'st-teresa-of-avila',
                'blessed-peter-canisius',
                'st-john-of-the-cross',
                'st-robert-francis-romulus-bellarmine',
                'st-lorenzo-da-brindisi',
                'st-francis-de-sales',
                'st-alphonsus-liguori',
                'st-th-r-se-of-lisieux',
                'bl-john-of-avila',
                'st-hildegard',
                'st-gregory-of-narek',
                'st-john-henry-newman',
                'st-irenaeus',
                'st-isidore-of-seville',
                'st-peter-chrysologus',
                'pope-st-leo-i-the-great',
            ])
            ->update([
                'canonical_status' => DB::raw("case when slug in ('blessed-peter-canisius', 'bl-john-of-avila', 'st-john-henry-newman', 'st-gregory-of-narek') then 'saint' else canonical_status end"),
                'is_doctor' => true,
                'updated_at' => $now,
            ]);

        foreach ([
            ['2511f6b9-78bb-5542-af12-e7734e26822e', 10, 9],
            ['5de180db-b6f1-5a0b-9470-9b90f8ad81fb', 2, 27],
        ] as [$saintId, $month, $day]) {
            DB::table('feast_days')->updateOrInsert(
                [
                    'saint_id' => $saintId,
                    'month' => $month,
                    'day' => $day,
                    'calendar' => 'general',
                ],
                [
                    'rite' => null,
                    'locality' => null,
                    'citation_id' => null,
                    'confidence' => 0.9,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('feast_days')
            ->whereIn('saint_id', [
                '2511f6b9-78bb-5542-af12-e7734e26822e',
                '5de180db-b6f1-5a0b-9470-9b90f8ad81fb',
            ])
            ->delete();

        DB::table('saints')
            ->whereIn('id', [
                '2511f6b9-78bb-5542-af12-e7734e26822e',
                '5de180db-b6f1-5a0b-9470-9b90f8ad81fb',
            ])
            ->delete();
    }

    private function upsertSaint(array $values): void
    {
        DB::table('saints')->updateOrInsert(['id' => $values['id']], $values);
    }

    private function documentText(string $slug): ?string
    {
        $document = DB::table('source_documents')
            ->where('slug', $slug)
            ->first(['raw_text']);

        return $document ? trim((string) $document->raw_text) : null;
    }

    /**
     * @param mixed $value
     */
    private function json($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
};
