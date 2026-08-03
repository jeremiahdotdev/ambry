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
            ->assertSee('Search...')
            ->assertSee('Search Filter')
            ->assertSee('Popular Filters')
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
            ->assertSee('Back to search')
            ->assertDontSee('Search...')
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

    public function test_popular_filters_render_on_the_first_page_and_filter_searches(): void
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

        $this->get('/?popular=patron_saints')
            ->assertOk()
            ->assertSee('Popular Filters')
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Patron Saints')
            ->assertDontSee('Patron Example');

        $this->get('/search?type=saint&popular=patron_saints')
            ->assertOk()
            ->assertSee('Back to search')
            ->assertSee('Patron Saints')
            ->assertSee('Patron Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?type=saint')
            ->assertOk()
            ->assertSee('<p>Searching</p>', false)
            ->assertSee('<h1>Saints</h1>', false)
            ->assertSee('Patron Example')
            ->assertSee('Unfiltered Example');

        $this->get('/search?q=Example&type=saint&popular=patron_saints')
            ->assertOk()
            ->assertSee('Patron Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?q=Example&type=saint&popular=patrons')
            ->assertOk()
            ->assertSee('Patron Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?q=Example&type=saint&popular=martyrs')
            ->assertOk()
            ->assertSee('Martyrs')
            ->assertSee('Martyr Example')
            ->assertDontSee('Saint Unfiltered Example');

        $this->get('/search?q=Example&type=saint&popular=men')
            ->assertOk()
            ->assertSee('Patron Example')
            ->assertDontSee('Woman Example');

        $this->get('/search?q=Example&type=saint&popular=women')
            ->assertOk()
            ->assertSee('Woman Example')
            ->assertDontSee('Patron Example');

        $this->get('/search?q=Example&type=saint&popular=doctors')
            ->assertOk()
            ->assertSee('Doctor Example')
            ->assertDontSee('Saint Unfiltered Example');
    }

    public function test_search_strips_honorific_prefixes_and_selects_the_matching_type(): void
    {
        Saint::create([
            'primary_name' => 'Saint Patrick',
            'slug' => 'saint-patrick',
            'canonical_status' => 'saint',
        ]);

        Saint::create([
            'primary_name' => 'Pope Leo XIII',
            'slug' => 'pope-leo-xiii',
            'canonical_status' => 'pope',
        ]);

        Saint::create([
            'primary_name' => 'Bl. Carlo Acutis',
            'slug' => 'bl-carlo-acutis',
            'canonical_status' => 'blessed',
        ]);

        Saint::create([
            'primary_name' => 'Ven. Mary Ward',
            'slug' => 'ven-mary-ward',
            'canonical_status' => 'venerable',
        ]);

        $this->get('/search?q=St.%20Patrick&type=pope')
            ->assertOk()
            ->assertSee('<h1>Saints</h1>', false)
            ->assertSee('Patrick')
            ->assertDontSee('Leo XIII');

        $this->get('/search?q=Pope%20St.%20Leo')
            ->assertOk()
            ->assertSee('<h1>Popes</h1>', false)
            ->assertSee('Leo XIII')
            ->assertDontSee('Patrick');

        $this->get('/search?q=Bl.%20Carlo')
            ->assertOk()
            ->assertSee('<h1>Blessed</h1>', false)
            ->assertSee('Carlo Acutis')
            ->assertDontSee('Patrick');

        $this->get('/search?q=Ven%20Mary')
            ->assertOk()
            ->assertSee('<h1>Venerable</h1>', false)
            ->assertSee('Mary Ward')
            ->assertDontSee('Patrick');
    }

    public function test_saint_page_renders_the_saint_layout(): void
    {
        $patrick = Saint::create([
            'primary_name' => 'St. Patrick',
            'slug' => 'st-patrick',
            'biography' => 'Apostle of Ireland.',
            'profile_subtitle' => 'Bishop and Patron of Ireland',
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
            ->assertSee('<svg class="saint-cross"', false)
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

    public function test_saint_page_adds_martyr_to_roles_when_marked_as_martyr(): void
    {
        Saint::create([
            'primary_name' => 'St. Martyr Role Example',
            'slug' => 'st-martyr-role-example',
            'biography' => 'A faithful witness.',
            'is_martyr' => true,
        ]);

        $this->get('/saints/st-martyr-role-example')
            ->assertOk()
            ->assertSee('<h2 id="saint-profile-roles-title">Roles</h2>', false)
            ->assertSee('<span>Martyr</span>', false);
    }

    public function test_saint_page_renders_profile_summary_blank_lines_as_paragraphs(): void
    {
        Saint::create([
            'primary_name' => 'St. Summary Example',
            'slug' => 'st-summary-example',
            'biography' => 'Fallback biography.',
            'profile_summary' => "First summary paragraph.\n\nSecond summary paragraph.",
        ]);

        $this->get('/saints/st-summary-example')
            ->assertOk()
            ->assertSeeInOrder([
                '<p>First summary paragraph.</p>',
                '<p>Second summary paragraph.</p>',
            ], false)
            ->assertDontSee("First summary paragraph.\n\nSecond summary paragraph.");
    }

    public function test_saint_page_uses_single_line_title_sizing_for_wide_names(): void
    {
        Saint::create([
            'primary_name' => 'St. Bartholomew',
            'slug' => 'st-bartholomew',
            'biography' => 'One of the Twelve.',
        ]);

        $this->get('/saints/st-bartholomew')
            ->assertOk()
            ->assertSee('<h1 class="saint-title saint-title--wide" style="--saint-title-length: 11;">Bartholomew</h1>', false);
    }

    public function test_saint_page_scales_temperament_scores_to_seventy_five_percent(): void
    {
        Saint::create([
            'primary_name' => 'St. Balanced Example',
            'slug' => 'st-balanced-example',
            'biography' => 'A steady witness.',
            'profile_temperaments' => [
                'primary' => 'choleric',
                'secondary' => 'melancholic',
                'scores' => [
                    'choleric' => 25,
                    'melancholic' => 10,
                    'sanguine' => 5,
                ],
            ],
        ]);

        $this->get('/saints/st-balanced-example')
            ->assertOk()
            ->assertSee('inline-size: 75%;', false)
            ->assertSee('inline-size: 30%;', false)
            ->assertSee('inline-size: 15%;', false)
            ->assertDontSee('<strong>75</strong>', false)
            ->assertDontSee('Primary:')
            ->assertDontSee('Secondary:');
    }

    public function test_saint_page_renders_virtues_and_vices_as_separate_sections(): void
    {
        Saint::create([
            'primary_name' => 'St. Interior Example',
            'slug' => 'st-interior-example',
            'biography' => 'A converted life.',
            'profile_key_virtues' => [
                [
                    'name' => 'patience',
                    'summary' => 'Waited with trust.',
                ],
            ],
            'profile_key_struggles' => [
                [
                    'name' => 'impatience',
                    'summary' => 'Had to grow in steadiness.',
                ],
            ],
        ]);

        $this->get('/saints/st-interior-example')
            ->assertOk()
            ->assertSee('<h2>Virtues</h2>', false)
            ->assertSee('<h2>Vices</h2>', false)
            ->assertSee('Waited with trust.')
            ->assertSee('Had to grow in steadiness.')
            ->assertDontSee('Interior Life')
            ->assertDontSee('saint-profile-two-column');
    }

    public function test_saint_page_renders_sources_and_research_notes_as_separate_collapsible_sections(): void
    {
        Saint::create([
            'primary_name' => 'St. Source Example',
            'slug' => 'st-source-example',
            'biography' => 'A documented life.',
            'profile_sources' => [
                [
                    'title' => 'Source Title',
                    'url' => 'https://example.com/source',
                    'accessed_date' => '2026-07-31',
                ],
            ],
            'profile_research_notes' => [
                'Needs follow-up on early chronology.',
            ],
        ]);

        $this->get('/saints/st-source-example')
            ->assertOk()
            ->assertSee('<summary><h2>Sources</h2></summary>', false)
            ->assertSee('<summary><h2>Research Notes</h2></summary>', false)
            ->assertSeeInOrder(['Research Notes', 'Sources'])
            ->assertSee('Source Title')
            ->assertSee('Needs follow-up on early chronology.')
            ->assertDontSee('Profile Sources')
            ->assertDontSee('<summary>Research notes</summary>', false);
    }

    public function test_saint_page_renders_landmark_locations_as_plain_text(): void
    {
        Saint::create([
            'primary_name' => 'St. Landmark Example',
            'slug' => 'st-landmark-example',
            'biography' => 'A place-bound witness.',
            'profile_landmarks' => [
                [
                    'name' => 'Ancient Church',
                    'location' => 'Old Road 12, Example City',
                    'description' => 'A remembered place.',
                ],
            ],
        ]);

        $this->get('/saints/st-landmark-example')
            ->assertOk()
            ->assertSee('<h2>Landmarks</h2>', false)
            ->assertSee('<p class="saint-profile-location">Old Road 12, Example City</p>', false)
            ->assertDontSee('<p class="saint-profile-meta"><span>Old Road 12, Example City</span></p>', false);
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
            ->assertSee('<span class="saint-kicker-label">Blessed</span>', false)
            ->assertDontSee('<span class="saint-kicker-label">Saint</span>', false)
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

    public function test_search_results_prefer_shortened_profile_summary_excerpt(): void
    {
        Saint::create([
            'primary_name' => 'Saint Summary Result',
            'slug' => 'saint-summary-result',
            'canonical_status' => 'saint',
            'biography' => 'Full biography text that should not appear on the search card.',
            'profile_summary' => str_repeat('Profile summary sentence. ', 20),
        ]);

        $this->get('/search?q=Summary&type=saint')
            ->assertOk()
            ->assertSee(\Illuminate\Support\Str::limit(\Illuminate\Support\Str::of(str_repeat('Profile summary sentence. ', 20))->squish(), 250))
            ->assertDontSee('Full biography text that should not appear on the search card.');
    }

    public function test_search_results_mark_cards_without_patronages(): void
    {
        $patronage = Patronage::create([
            'name' => 'Teachers',
            'slug' => 'teachers',
        ]);

        $withPatronage = Saint::create([
            'primary_name' => 'Saint Patronage Class',
            'slug' => 'saint-patronage-class',
            'canonical_status' => 'saint',
        ]);

        $withPatronage->patronages()->attach($patronage);

        Saint::create([
            'primary_name' => 'Saint No Patronage Class',
            'slug' => 'saint-no-patronage-class',
            'canonical_status' => 'saint',
        ]);

        $this->get('/search?q=Patronage%20Class&type=saint')
            ->assertOk()
            ->assertSee('search-result search-result--without-patronages', false)
            ->assertSee('search-result-patronages', false);
    }

    public function test_search_results_are_paginated_ten_at_a_time(): void
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
            ->assertSee('10+ results')
            ->assertSee('Pagination 01')
            ->assertSee('Pagination 10')
            ->assertDontSee('Pagination 11')
            ->assertSee('/search?q=Pagination&amp;type=saint&amp;page=2', false);

        $this->get('/search?q=Pagination&type=saint&page=2')
            ->assertOk()
            ->assertSee('Pagination 11')
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
            ->assertJsonPath('data.0.name', 'Patrick')
            ->assertJsonPath('data.0.slug', 'saint-patrick')
            ->assertJsonMissingPath('source_documents');
    }
}
