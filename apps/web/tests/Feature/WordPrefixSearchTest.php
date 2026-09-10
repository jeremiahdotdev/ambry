<?php

namespace Tests\Feature;

use App\Models\Patronage;
use App\Models\Saint;
use App\Services\SaintSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPrefixSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocomplete_and_results_match_word_starts_instead_of_substrings(): void
    {
        foreach (['Thérèse', 'Saint Marie-Therese', 'Saint Catherine', 'Saint Éther'] as $index => $name) {
            Saint::create(['primary_name' => $name, 'slug' => 'example-'.$index, 'canonical_status' => 'saint']);
        }

        $this->getJson('/search/suggestions?q=Ther')->assertOk()
            ->assertJsonCount(2, 'suggestions')
            ->assertJsonMissing(['name' => 'Catherine']);
        $this->get('/search?q=Ther')->assertOk()
            ->assertSee('Thérèse')->assertSee('Marie-Therese')->assertDontSee('Catherine')->assertDontSee('Éther');

    }

    public function test_aliases_and_broad_search_fields_also_match_word_starts(): void
    {
        $alias = Saint::create(['primary_name' => 'Alias Match', 'slug' => 'alias']);
        $alias->aliases()->create(['alias' => 'Marie Therese', 'normalized_alias' => 'marie therese']);
        Saint::create(['primary_name' => 'Virtue Match', 'slug' => 'virtue', 'virtues' => ['therapeutic care']]);
        Saint::create(['primary_name' => 'Vice Match', 'slug' => 'vice', 'vices' => ['thermal discomfort']]);
        $patron = Saint::create(['primary_name' => 'Patron Match', 'slug' => 'patron']);
        $patron->patronages()->attach(Patronage::create([
            'name' => 'Healers', 'slug' => 'healers', 'description' => 'For therapists.',
        ]));
        $nonmatch = Saint::create([
            'primary_name' => 'Unmatched', 'slug' => 'unmatched', 'virtues' => ['motherly care'], 'vices' => ['bothered'],
        ]);
        $nonmatch->aliases()->create(['alias' => 'Catherine', 'normalized_alias' => 'catherine']);
        $nonmatch->patronages()->attach(Patronage::create([
            'name' => 'Mothers', 'slug' => 'mothers', 'description' => 'For mothers.',
        ]));

        $this->assertSame(['alias', 'patron', 'vice', 'virtue'], app(SaintSearchService::class)->search('ther')->pluck('slug')->all());
    }

    public function test_query_punctuation_is_literal_and_unicode_word_prefixes_work(): void
    {
        Saint::create(['primary_name' => 'Saint Thérèse', 'slug' => 'therese']);
        $search = app(SaintSearchService::class);
        $this->assertSame(['therese'], $search->search('THÉR')->pluck('slug')->all());
        $this->assertSame(['therese'], $search->search('Ther')->pluck('slug')->all());

        foreach (['Th%', 'Th_', 'Th.*', 'Th[', 'Th\\', 'Th~'] as $query) {
            $this->assertCount(0, $search->search($query));
        }
    }
}
