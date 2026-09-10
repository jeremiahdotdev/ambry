<?php

namespace Tests\Feature;

use App\Livewire\ApiKeys;
use App\Livewire\EditorStaff;
use App\Livewire\Login;
use App\Livewire\Register;
use App\Livewire\SaintEditor;
use App\Livewire\Search;
use App\Models\Saint;
use App\Models\SaintEditorPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_suggestions_respect_aliases_prefixes_and_filters(): void
    {
        $saint = Saint::create(['primary_name' => 'Saint Patrick', 'slug' => 'patrick', 'canonical_status' => 'saint', 'is_martyr' => true]);
        $saint->aliases()->create(['alias' => 'Patricius', 'normalized_alias' => 'patricius']);
        Saint::create(['primary_name' => 'Pope Patrick', 'slug' => 'pope-patrick', 'canonical_status' => 'pope']);

        Livewire::test(Search::class)->set('query', 'Patricius')->call('togglePopular', 'martyrs')
            ->assertViewHas('suggestions', fn ($items) => count($items) === 1 && $items[0]['name'] === 'Patrick')
            ->call('togglePopular', 'women')->assertViewHas('suggestions', [])
            ->call('togglePopular', 'women')->set('query', 'Pope Patrick')
            ->assertViewHas('suggestions', fn ($items) => count($items) === 1 && $items[0]['type'] === 'Pope')
            ->call('search')->assertRedirect(route('search.results', ['q' => 'Pope Patrick', 'type' => 'saint', 'popular' => '']));
    }

    public function test_results_paginate_in_place_and_filter_changes_reset_page(): void
    {
        foreach (range(1, 12) as $number) {
            Saint::create(['primary_name' => sprintf('Saint Example %02d', $number), 'slug' => 'example-'.$number, 'canonical_status' => 'saint']);
        }
        Livewire::withQueryParams(['q' => 'Example'])->test(Search::class, ['resultsPage' => true])
            ->assertSee('Example 01')->assertDontSee('Example 11')
            ->call('nextPage')->assertSee('Example 11')->assertDontSee('Example 01')
            ->set('query', 'Example 01')->assertSet('paginators.page', 1)->assertSee('Example 01');
    }

    public function test_editor_saves_in_place_validates_and_rechecks_revoked_access(): void
    {
        $user = User::factory()->create(['email' => 'editor@example.com']);
        $permission = SaintEditorPermission::create(['email' => $user->email, 'role' => 'editor']);
        $saint = Saint::create(['primary_name' => 'Saint Example', 'slug' => 'example', 'canonical_status' => 'saint']);
        $component = Livewire::actingAs($user)->test(SaintEditor::class, ['saint' => $saint])
            ->set('form.primary_name', '')->call('save')->assertHasErrors(['form.primary_name' => 'required'])
            ->set('form.primary_name', 'Saint Updated')->set('form.is_martyr', true)
            ->set('form.birth_year', '')->call('save')->assertHasNoErrors()->assertSee('Saint updated.');
        $this->assertSame('Saint Updated', $saint->fresh()->primary_name);
        $this->assertTrue($saint->fresh()->is_martyr);
        $this->assertNull($saint->fresh()->birth_year);
        $component->set('form.primary_name', 'Forbidden');
        $permission->delete();
        $component->call('save')->assertForbidden();
        $this->assertSame('Saint Updated', $saint->fresh()->primary_name);
    }

    public function test_editor_redirects_to_the_new_slug_after_rename(): void
    {
        $owner = User::factory()->create(['email' => 'jeremiahdgage@outlook.com']);
        $saint = Saint::create(['primary_name' => 'Saint Example', 'slug' => 'example', 'canonical_status' => 'saint']);
        Livewire::actingAs($owner)->test(SaintEditor::class, ['saint' => $saint])
            ->set('form.slug', 'renamed')->call('save')->assertHasNoErrors()
            ->assertRedirect(route('saints.edit', 'renamed'));
    }

    public function test_staff_management_restricts_actions_and_protects_owner(): void
    {
        $owner = User::factory()->create(['email' => 'jeremiahdgage@outlook.com']);
        $component = Livewire::actingAs($owner)->test(EditorStaff::class)
            ->set('email', 'new-editor@example.com')->call('add')->assertSee('new-editor@example.com');
        $permission = SaintEditorPermission::where('email', 'new-editor@example.com')->firstOrFail();
        $component->call('remove', $permission->id)->assertDontSee('new-editor@example.com');
        $ownerPermission = SaintEditorPermission::where('email', $owner->email)->firstOrFail();
        $component->call('remove', $ownerPermission->id)->assertHasErrors('email');
        Livewire::actingAs(User::factory()->create())->test(EditorStaff::class)->assertForbidden();
    }

    public function test_key_creation_validation_and_ownership_are_enforced(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(ApiKeys::class)
            ->call('create')->assertHasErrors(['name' => 'required'])
            ->set('name', 'Livewire key')->call('create')->assertHasNoErrors()->assertSee('Livewire key');
        $key = $user->developerApiKeys()->firstOrFail();
        $component->assertDontSee($key->token_hash)->assertDontSee($key->prefix)
            ->call('revoke', $key->id)->assertSee('Revoked');
        $this->assertNotNull($key->fresh()->revoked_at);
        $other = User::factory()->create();
        $foreign = $other->developerApiKeys()->create(['name' => 'Foreign', 'prefix' => 'foreign', 'token_hash' => hash('sha256', 'foreign')]);
        $component->call('revoke', $foreign->id)->assertNotFound();
        $this->assertNull($foreign->fresh()->revoked_at);
    }

    public function test_login_and_logout_use_livewire(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong')
            ->call('login')->assertHasErrors('email')->assertSet('password', '')
            ->set('password', 'secret-password')->call('login')->assertHasNoErrors()
            ->assertRedirect(route('developers.api-keys.index'));
        $this->assertAuthenticatedAs($user);
        Livewire::test(ApiKeys::class)->call('logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signup_validates_confirmation_and_authenticates(): void
    {
        Livewire::test(Register::class)->set('name', 'New User')->set('email', 'signup@example.com')
            ->set('password', 'secret-password')->set('password_confirmation', 'wrong')->call('register')
            ->assertHasErrors(['password' => 'confirmed'])
            ->set('password_confirmation', 'secret-password')->call('register')->assertHasNoErrors()
            ->assertRedirect(route('developers.api-keys.index'))->assertSet('password', '');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'signup@example.com']);
    }
}
