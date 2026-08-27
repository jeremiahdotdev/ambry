<?php

namespace Tests\Feature;

use App\Models\Saint;
use App\Models\SaintEditorPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaintEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_permission_is_seeded_by_the_data_layer(): void
    {
        $this->assertDatabaseHas('saint_editor_permissions', [
            'email' => 'jeremiahdgage@outlook.com',
            'role' => 'owner',
        ]);
    }

    public function test_editor_button_only_renders_for_allowed_staff(): void
    {
        $saint = $this->saint();
        $owner = User::factory()->create([
            'email' => 'jeremiahdgage@outlook.com',
        ]);

        $this
            ->get(route('saints.profile', $saint))
            ->assertOk()
            ->assertDontSee('Edit Saint')
            ->assertDontSee(route('saints.edit', $saint));

        $this
            ->actingAs(User::factory()->create())
            ->get(route('saints.profile', $saint))
            ->assertOk()
            ->assertDontSee('Edit Saint')
            ->assertDontSee(route('saints.edit', $saint));

        $this
            ->actingAs($owner)
            ->get(route('saints.profile', $saint))
            ->assertOk()
            ->assertSee('Edit Saint')
            ->assertSee(route('saints.edit', $saint));
    }

    public function test_only_staff_can_open_and_update_the_saint_editor(): void
    {
        $saint = $this->saint();
        $editor = User::factory()->create([
            'email' => 'editor@example.com',
        ]);

        SaintEditorPermission::query()->create([
            'email' => 'editor@example.com',
            'role' => 'editor',
        ]);

        $this
            ->get(route('saints.edit', $saint))
            ->assertRedirect(route('login'));

        $this
            ->actingAs(User::factory()->create())
            ->get(route('saints.edit', $saint))
            ->assertForbidden();

        $this
            ->actingAs($editor)
            ->get(route('saints.edit', $saint))
            ->assertOk()
            ->assertSee('Edit Patrick');

        $response = $this
            ->actingAs($editor)
            ->patch(route('saints.update', $saint), $this->validPayload([
                'primary_name' => 'Saint Padraig',
                'slug' => 'saint-padraig',
                'profile_subtitle' => 'Apostle of Ireland',
                'profile_summary' => 'A missionary bishop remembered for courage.',
                'biography' => 'Updated biography.',
                'is_martyr' => '1',
                'image_page_variant' => 'celtic-green',
            ]));

        $response
            ->assertRedirect(route('saints.profile', $saint->fresh()))
            ->assertSessionHas('status', 'Saint updated.');

        $this->assertDatabaseHas('saints', [
            'id' => $saint->id,
            'primary_name' => 'Saint Padraig',
            'slug' => 'saint-padraig',
            'canonical_status' => 'saint',
            'is_martyr' => true,
            'is_doctor' => false,
            'profile_subtitle' => 'Apostle of Ireland',
            'profile_summary' => 'A missionary bishop remembered for courage.',
            'biography' => 'Updated biography.',
            'image_page_variant' => 'celtic-green',
        ]);
    }

    public function test_non_staff_cannot_post_directly_to_the_saint_editor(): void
    {
        $saint = $this->saint();

        $this
            ->actingAs(User::factory()->create())
            ->patch(route('saints.update', $saint), $this->validPayload([
                'primary_name' => 'Saint Changed',
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('saints', [
            'id' => $saint->id,
            'primary_name' => 'Saint Patrick',
        ]);
    }

    public function test_only_the_owner_can_manage_edit_staff(): void
    {
        $owner = User::factory()->create([
            'email' => 'jeremiahdgage@outlook.com',
        ]);
        $editor = User::factory()->create([
            'email' => 'editor@example.com',
        ]);
        $staffPermission = SaintEditorPermission::query()->create([
            'email' => 'editor@example.com',
            'role' => 'editor',
        ]);

        $this
            ->actingAs($editor)
            ->get(route('saints.edit-staff.index'))
            ->assertForbidden();

        $this
            ->actingAs($editor)
            ->post(route('saints.edit-staff.store'), ['email' => 'new-editor@example.com'])
            ->assertForbidden();

        $this
            ->actingAs($editor)
            ->delete(route('saints.edit-staff.destroy', $staffPermission))
            ->assertForbidden();

        $this
            ->actingAs($owner)
            ->get(route('saints.edit-staff.index'))
            ->assertOk()
            ->assertSee('jeremiahdgage@outlook.com')
            ->assertSee('Owner')
            ->assertSee('editor@example.com');

        $this
            ->actingAs($owner)
            ->post(route('saints.edit-staff.store'), ['email' => 'NEW-EDITOR@EXAMPLE.COM'])
            ->assertRedirect(route('saints.edit-staff.index'))
            ->assertSessionHas('status', 'Editor added.');

        $this->assertDatabaseHas('saint_editor_permissions', [
            'email' => 'new-editor@example.com',
            'role' => 'editor',
        ]);

        $this
            ->actingAs($owner)
            ->delete(route('saints.edit-staff.destroy', $staffPermission))
            ->assertRedirect(route('saints.edit-staff.index'))
            ->assertSessionHas('status', 'Editor removed.');

        $this->assertDatabaseMissing('saint_editor_permissions', [
            'email' => 'editor@example.com',
        ]);
    }

    public function test_owner_cannot_remove_the_owner_permission(): void
    {
        $owner = User::factory()->create([
            'email' => 'jeremiahdgage@outlook.com',
        ]);
        $ownerPermission = SaintEditorPermission::query()
            ->where('email', 'jeremiahdgage@outlook.com')
            ->firstOrFail();

        $this
            ->actingAs($owner)
            ->delete(route('saints.edit-staff.destroy', $ownerPermission))
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('saint_editor_permissions', [
            'email' => 'jeremiahdgage@outlook.com',
            'role' => 'owner',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'primary_name' => 'Saint Patrick',
            'slug' => 'saint-patrick',
            'canonical_status' => 'saint',
            'gender' => 'male',
            'birth_year' => '387',
            'birth_year_qualifier' => 'circa',
            'death_year' => '493',
            'death_year_qualifier' => 'circa',
            'life_dates' => 'c. 387-c. 493 AD',
            'is_martyr' => '0',
            'is_doctor' => '0',
            'profile_subtitle' => '',
            'profile_summary' => '',
            'biography' => 'Missionary associated with Ireland.',
            'image_page_variant' => '',
        ], $overrides);
    }

    private function saint(): Saint
    {
        return Saint::query()->create([
            'primary_name' => 'Saint Patrick',
            'slug' => 'saint-patrick',
            'biography' => 'Missionary associated with Ireland.',
            'life_dates' => '387-493 AD',
            'canonical_status' => 'saint',
        ]);
    }
}
