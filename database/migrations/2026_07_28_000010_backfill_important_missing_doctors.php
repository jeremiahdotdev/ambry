<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->upsertSaint([
            'id' => '2cf8b5a4-4678-538c-b99e-1f0388e9095d',
            'primary_name' => 'St. Augustine of Hippo',
            'slug' => 'st-augustine-of-hippo',
            'biography' => $this->combinedBiography([
                'cathen-02084a',
                'cathen-02089a',
                'cathen-02091a',
            ]) ?: 'Bishop of Hippo, theologian, philosopher, and Doctor of the Church.',
            'birth_year' => 354,
            'birth_year_qualifier' => 'exact',
            'death_year' => 430,
            'death_year_qualifier' => 'exact',
            'life_dates' => '354-430 AD',
            'gender' => 'male',
            'canonical_status' => 'saint',
            'is_martyr' => false,
            'is_doctor' => true,
            'virtues' => $this->json(['conversion', 'wisdom', 'penitence', 'zeal', 'perseverance']),
            'vices' => $this->json(['pride', 'lust', 'ambition']),
            'roles' => $this->json(['bishop', 'theologian', 'philosopher', 'doctor of the church']),
            'ai_reason' => 'Backfilled from New Advent Catholic Encyclopedia Augustine of Hippo source articles.',
            'ai_confidence' => 0.95,
            'image_prompt' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertSaint([
            'id' => 'b05df66a-8973-5f0a-81c5-edf96ec900de',
            'primary_name' => 'St. Bede the Venerable',
            'slug' => 'st-bede-the-venerable',
            'biography' => $this->combinedBiography(['cathen-02384a'])
                ?: 'English monk, historian, biblical scholar, and Doctor of the Church.',
            'birth_year' => 673,
            'birth_year_qualifier' => 'circa',
            'death_year' => 735,
            'death_year_qualifier' => 'exact',
            'life_dates' => 'c. 673-735 AD',
            'gender' => 'male',
            'canonical_status' => 'saint',
            'is_martyr' => false,
            'is_doctor' => true,
            'virtues' => $this->json(['learning', 'humility', 'perseverance', 'wisdom']),
            'vices' => $this->json([]),
            'roles' => $this->json(['monk', 'historian', 'biblical scholar', 'doctor of the church']),
            'ai_reason' => 'Backfilled from New Advent Catholic Encyclopedia Venerable Bede source article.',
            'ai_confidence' => 0.92,
            'image_prompt' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['2cf8b5a4-4678-538c-b99e-1f0388e9095d', 8, 28],
            ['b05df66a-8973-5f0a-81c5-edf96ec900de', 5, 25],
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
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        DB::table('saints')
            ->whereIn('slug', [
                'st-jerome',
                'st-basil-the-great',
                'st-hilary-of-poitiers',
                'st-john-damascene',
                'st-albertus-magnus',
                'st-anthony-of-padua',
                'st-catherine-of-siena',
                'st-teresa-of-avila',
                'blessed-peter-canisius',
                'st-john-of-the-cross',
                'st-robert-francis-romulus-bellarmine',
                'st-irenaeus',
                'st-isidore-of-seville',
                'st-peter-chrysologus',
                'bl-john-of-avila',
                'st-hildegard',
            ])
            ->update([
                'canonical_status' => 'saint',
                'is_doctor' => true,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('feast_days')
            ->whereIn('saint_id', [
                '2cf8b5a4-4678-538c-b99e-1f0388e9095d',
                'b05df66a-8973-5f0a-81c5-edf96ec900de',
            ])
            ->delete();

        DB::table('saints')
            ->whereIn('id', [
                '2cf8b5a4-4678-538c-b99e-1f0388e9095d',
                'b05df66a-8973-5f0a-81c5-edf96ec900de',
            ])
            ->delete();
    }

    private function upsertSaint(array $values): void
    {
        $update = $values;
        unset($update['id'], $update['created_at']);

        DB::table('saints')->updateOrInsert(['id' => $values['id']], $values);
    }

    /**
     * @param list<string> $slugs
     */
    private function combinedBiography(array $slugs): ?string
    {
        $documents = DB::table('source_documents')
            ->whereIn('slug', $slugs)
            ->orderByRaw($this->orderBySlugsSql($slugs), $slugs)
            ->get(['title', 'raw_text']);

        if ($documents->isEmpty()) {
            return null;
        }

        return $documents
            ->map(fn (object $document): string => trim((string) $document->raw_text))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * @param list<string> $slugs
     */
    private function orderBySlugsSql(array $slugs): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return 'array_position(array['.implode(',', array_fill(0, count($slugs), '?')).']::text[], slug::text)';
        }

        return 'case slug '.implode(' ', array_map(
            fn (int $index): string => 'when ? then '.$index,
            array_keys($slugs),
        )).' end';
    }

    /**
     * @param mixed $value
     */
    private function json($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
};
