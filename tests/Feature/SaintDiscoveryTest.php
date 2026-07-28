<?php

namespace Tests\Feature;

use App\Models\Patronage;
use App\Models\Saint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
            ->assertSee('Search saints...')
            ->assertDontSee('Church Father')
            ->assertDontSee('Church Fathers');
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

    public function test_popular_search_links_filter_saint_results(): void
    {
        $patron = Saint::create([
            'primary_name' => 'Saint Patron Example',
            'slug' => 'saint-patron-example',
            'canonical_status' => 'saint',
            'gender' => 'male',
        ]);

        $patron->patronages()->attach(Patronage::create([
            'name' => 'Libraries',
            'slug' => 'libraries',
        ]));

        Saint::create([
            'primary_name' => 'Saint Martyr Example',
            'slug' => 'saint-martyr-example',
            'canonical_status' => 'saint',
            'is_martyr' => true,
        ]);

        Saint::create([
            'primary_name' => 'Saint Woman Example',
            'slug' => 'saint-woman-example',
            'canonical_status' => 'saint',
            'gender' => 'female',
        ]);

        Saint::create([
            'primary_name' => 'Saint Doctor Example',
            'slug' => 'saint-doctor-example',
            'canonical_status' => 'saint',
            'is_doctor' => true,
        ]);

        Saint::create([
            'primary_name' => 'Saint Unfiltered Example',
            'slug' => 'saint-unfiltered-example',
            'canonical_status' => 'saint',
        ]);

        $this->get('/search?type=saint&popular=patron_saints')
            ->assertOk()
            ->assertSee('Patron Saints')
            ->assertSee('popular=patron_saints', false)
            ->assertSee('Patron Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?type=saint&popular=patrons')
            ->assertOk()
            ->assertSee('Patron Saints')
            ->assertSee('Patron Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?type=saint&popular=martyrs')
            ->assertOk()
            ->assertSee('Martyr Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?type=saint&popular=men')
            ->assertOk()
            ->assertSee('Patron Example')
            ->assertDontSee('Woman Example');

        $this->get('/search?type=saint&popular=women')
            ->assertOk()
            ->assertSee('Woman Example')
            ->assertDontSee('Patron Example');

        $this->get('/search?type=saint&popular=doctors')
            ->assertOk()
            ->assertSee('Doctor Example')
            ->assertDontSee('Saint Unfiltered Example');
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

    public function test_saint_page_uses_generated_portrait_and_variant_when_available(): void
    {
        $francis = Saint::create([
            'primary_name' => 'St. Generated Example',
            'slug' => 'st-generated-example',
            'biography' => 'Franciscan friar.',
        ]);
        $generatedDir = storage_path("app/generated/saints/{$francis->slug}");

        File::ensureDirectoryExists($generatedDir);
        File::put("{$generatedDir}/portrait.webp", 'webp');
        File::put("{$generatedDir}/thumb.webp", 'webp');
        File::put("{$generatedDir}/metadata.json", json_encode([
            'design_recommendation' => [
                'recommended_page_variant' => 'monastic-olive',
            ],
        ]));

        try {
            $this->get('/saints/st-generated-example')
                ->assertOk()
                ->assertSee(route('generated.saint-image', ['slug' => $francis->slug, 'kind' => 'portrait']))
                ->assertSee('data-saint-variant="monastic-olive"', false);

            $this->get(route('generated.saint-image', ['slug' => $francis->slug, 'kind' => 'portrait']))
                ->assertOk();
        } finally {
            File::deleteDirectory($generatedDir);
        }
    }

    public function test_saint_page_kicker_uses_the_canonical_status(): void
    {
        Saint::create([
            'primary_name' => 'Bl. Adrian Fortescue',
            'slug' => 'bl-adrian-fortescue',
            'canonical_status' => 'blessed',
            'biography' => 'Knight of St. John and martyr.',
        ]);

        $this->get('/saints/bl-adrian-fortescue')
            ->assertOk()
            ->assertSee('<span>Blessed</span>', false)
            ->assertDontSee('<span>Saint</span>', false)
            ->assertSee('Adrian Fortescue');
    }

    public function test_saint_display_helpers_remove_honorifics_and_repeated_biography_heading(): void
    {
        $leo = Saint::create([
            'primary_name' => 'Pope St. Leo I',
            'slug' => 'pope-st-leo-i',
            'canonical_status' => 'pope',
            'biography' => "Pope St. Leo I\n\nDoctor of the Church and bishop of Rome.",
        ]);

        $this->assertSame('Leo I', $leo->displayName());
        $this->assertSame('Doctor of the Church and bishop of Rome.', $leo->displayBiography());

        $this->getJson('/api/saints/pope-st-leo-i')
            ->assertOk()
            ->assertJsonPath('data.name', 'Leo I')
            ->assertJsonPath('data.biography', 'Doctor of the Church and bishop of Rome.');
    }

    public function test_api_returns_cleaned_blessed_and_venerable_names(): void
    {
        Saint::create([
            'primary_name' => 'Bl. Adrian Fortescue',
            'slug' => 'bl-adrian-fortescue',
            'canonical_status' => 'blessed',
        ]);

        Saint::create([
            'primary_name' => 'Ven. Mary Ward',
            'slug' => 'ven-mary-ward',
            'canonical_status' => 'venerable',
        ]);

        $this->getJson('/api/saints/bl-adrian-fortescue')
            ->assertOk()
            ->assertJsonPath('data.name', 'Adrian Fortescue');

        $this->getJson('/api/saints/ven-mary-ward')
            ->assertOk()
            ->assertJsonPath('data.name', 'Mary Ward');
    }

    public function test_search_results_use_cleaned_biography_excerpt(): void
    {
        Saint::create([
            'primary_name' => 'Bl. Adrian Fortescue',
            'slug' => 'bl-adrian-fortescue',
            'canonical_status' => 'blessed',
            'biography' => "Bl. Adrian Fortescue\n\nKnight of St. John and martyr.",
        ]);

        $this->get('/search?q=Adrian&type=blessed')
            ->assertOk()
            ->assertSee('Adrian Fortescue')
            ->assertSee('Knight of St. John and martyr.')
            ->assertDontSee('Bl. Adrian Fortescue');
    }

    public function test_search_results_are_paginated_twenty_at_a_time(): void
    {
        foreach (range(1, 21) as $index) {
            Saint::create([
                'primary_name' => sprintf('Saint Pagination %02d', $index),
                'slug' => sprintf('saint-pagination-%02d', $index),
                'canonical_status' => 'saint',
            ]);
        }

        $this->get('/search?q=Pagination&type=saint')
            ->assertOk()
            ->assertSee('20 results')
            ->assertSee('Pagination 01')
            ->assertSee('Pagination 20')
            ->assertDontSee('Pagination 21')
            ->assertSee('/search?q=Pagination&amp;type=saint&amp;page=2', false);

        $this->get('/search?q=Pagination&type=saint&page=2')
            ->assertOk()
            ->assertSee('Pagination 21')
            ->assertDontSee('Pagination 01');
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
