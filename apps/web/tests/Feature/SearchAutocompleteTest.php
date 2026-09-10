<?php

namespace Tests\Feature;

use App\Models\Saint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_match_aliases_and_respect_filters_and_prefixes(): void
    {
        $saint = Saint::create([
            'primary_name' => 'Saint Patrick', 'slug' => 'patrick',
            'canonical_status' => 'saint', 'is_martyr' => true,
        ]);
        $saint->aliases()->create(['alias' => 'Patricius', 'normalized_alias' => 'patricius']);
        Saint::create([
            'primary_name' => 'Pope Patrick', 'slug' => 'pope-patrick', 'canonical_status' => 'pope',
        ]);

        $this->getJson('/search/suggestions?q=Patricius&popular=martyrs')
            ->assertOk()->assertExactJson(['suggestions' => [[
                'name' => 'Patrick', 'type' => 'Saint', 'url' => route('saints.profile', $saint),
            ]]]);
        $this->getJson('/search/suggestions?q=Patrick&popular=women')
            ->assertOk()->assertExactJson(['suggestions' => []]);
        $this->getJson('/search/suggestions?q=Pope%20Patrick&type=saint')
            ->assertOk()->assertJsonCount(1, 'suggestions')->assertJsonPath('suggestions.0.type', 'Pope');
    }

    public function test_suggestions_are_limited_and_ignore_pagination(): void
    {
        foreach (range(1, 9) as $index) {
            Saint::create([
                'primary_name' => "Saint Example {$index}", 'slug' => "example-{$index}",
                'canonical_status' => 'saint',
            ]);
        }

        $this->getJson('/search/suggestions?q=Example&page=2')
            ->assertOk()->assertJsonCount(6, 'suggestions')->assertJsonPath('suggestions.0.name', 'Example 1');
    }

    public function test_short_and_unmatched_queries_return_no_suggestions(): void
    {
        foreach (['', 'a', 'St.%20a', 'unmatched'] as $query) {
            $this->getJson('/search/suggestions?q='.$query)
                ->assertOk()->assertExactJson(['suggestions' => []]);
        }
        $this->getJson('/search/suggestions?q[]=Patrick')->assertUnprocessable();
        $this->getJson('/search/suggestions?q='.str_repeat('a', 201))->assertUnprocessable();
    }

    public function test_search_renders_the_autocomplete_and_themed_empty_state(): void
    {
        $this->get('/')->assertOk()->assertSee('role="combobox"', false)->assertSee('data-search-autocomplete', false);
        $this->get('/search?q=unmatched')->assertOk()
            ->assertSee('search-no-results', false)->assertSee('No saints matched.')
            ->assertSee('Try another name, virtue, or patronage');
    }
}
