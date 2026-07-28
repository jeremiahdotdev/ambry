<?php

namespace Tests\Feature;

use App\Models\Patronage;
use App\Models\Saint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaintDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_the_saints_search_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Ambry')
            ->assertSee('Search by name, virtue, or keyword')
            ->assertSee('Search saints...');
    }

    public function test_search_populates_saint_entries_after_submit(): void
    {
        $patrick = Saint::create([
            'primary_name' => 'Saint Patrick',
            'slug' => 'saint-patrick',
            'biography' => 'Missionary associated with Ireland.',
            'life_dates' => '387-493 AD',
        ]);
        $patrick->aliases()->create([
            'alias' => 'Patricius',
            'normalized_alias' => 'patricius',
        ]);
        $patrick->patronages()->attach(Patronage::create([
            'name' => 'Ireland',
            'slug' => 'ireland',
        ]));

        Saint::create([
            'primary_name' => 'Saint Benedict of Nursia',
            'slug' => 'saint-benedict-of-nursia',
        ]);

        Saint::create([
            'primary_name' => 'Saint Brigid',
            'slug' => 'saint-brigid',
            'biography' => 'A later passage mentions Patrick.',
        ]);

        $this->get('/search?q=patricius')
            ->assertOk()
            ->assertSee('Patrick')
            ->assertSee('Ireland')
            ->assertSee(route('saints.profile', $patrick))
            ->assertDontSee('Saint Benedict of Nursia');

        $this->get('/search?q=Patrick')
            ->assertOk()
            ->assertSee('Patrick')
            ->assertSee('387-493 AD')
            ->assertDontSee('Saint Brigid');
    }

    public function test_search_scans_virtues_vices_and_patronages(): void
    {
        $monica = Saint::create([
            'primary_name' => 'Saint Monica',
            'slug' => 'saint-monica',
            'virtues' => ['patience', 'perseverance'],
            'vices' => ['despair'],
        ]);

        $patronage = Patronage::create([
            'name' => 'Mothers',
            'slug' => 'mothers',
            'description' => 'Parents seeking conversion in their families.',
        ]);

        $monica->patronages()->attach($patronage);

        Saint::create([
            'primary_name' => 'Saint Augustine',
            'slug' => 'saint-augustine',
            'virtues' => ['wisdom'],
            'vices' => ['pride'],
        ]);

        $this->get('/search?q=perseverance')
            ->assertOk()
            ->assertSee('Monica')
            ->assertDontSee('Saint Augustine');

        $this->get('/search?q=despair')
            ->assertOk()
            ->assertSee('Monica')
            ->assertDontSee('Saint Augustine');

        $this->get('/search?q=conversion')
            ->assertOk()
            ->assertSee('Monica')
            ->assertDontSee('Saint Augustine');
    }

    public function test_saint_page_renders_the_saint_layout(): void
    {
        $patrick = Saint::create([
            'primary_name' => 'St. Patrick',
            'slug' => 'st-patrick',
            'biography' => 'Apostle of Ireland.',
            'birth_year' => 387,
            'death_year' => 493,
            'life_dates' => '387 AD - 493 AD',
        ]);

        $patrick->patronages()->attach(Patronage::create([
            'name' => 'Ireland',
            'slug' => 'ireland',
        ]));

        $this->get('/saints/st-patrick')
            ->assertOk()
            ->assertSee('Patrick')
            ->assertSee('Bishop and Patron of Ireland')
            ->assertSee('387-493 AD')
            ->assertSee('Patronages')
            ->assertSee('Ireland')
            ->assertSee(route('search.results', ['q' => 'Ireland']))
            ->assertSeeInOrder(['Patronages', 'Ireland', 'Apostle of Ireland.'])
            ->assertSee('saints/st-patrick.png')
            ->assertSee('Back to search')
            ->assertSee('Apostle of Ireland.');
    }

    public function test_api_returns_saint_search_results(): void
    {
        Saint::create([
            'primary_name' => 'Saint Patrick',
            'slug' => 'saint-patrick',
            'biography' => 'Missionary associated with Ireland.',
        ]);

        $this->getJson('/api/search?q=Patrick')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Saint Patrick')
            ->assertJsonPath('data.0.slug', 'saint-patrick')
            ->assertJsonMissingPath('source_documents');
    }
}
