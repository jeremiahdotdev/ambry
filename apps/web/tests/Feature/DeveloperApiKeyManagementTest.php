<?php

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeveloperApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_api_key_page(): void
    {
        $this
            ->get(route('developers.api-keys.index'))
            ->assertRedirect(route('login'));
    }

    public function test_developers_landing_redirects_to_api_keys(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->get(route('developers.index'))
            ->assertRedirect(route('developers.api-keys.index'));
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Login');

        $this
            ->post(route('login.store'), [
                'email' => 'developer@example.com',
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('developers.api-keys.index'));

        $this->assertAuthenticatedAs($user);

        $this
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_sign_up(): void
    {
        $this
            ->get(route('register'))
            ->assertOk()
            ->assertSee('Signup')
            ->assertSee('Login');

        $this
            ->post(route('register.store'), [
                'name' => 'New Developer',
                'email' => 'new-developer@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('developers.api-keys.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'New Developer',
            'email' => 'new-developer@example.com',
        ]);
    }

    public function test_user_can_create_and_see_api_key_once(): void
    {
        Carbon::setTestNow('2026-08-04 10:00:00');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('developers.api-keys.store'), [
                'name' => 'Local dashboard',
                'expires_at' => '2026-09-01',
            ]);

        $response->assertRedirect(route('developers.api-keys.index'));

        $apiKey = DeveloperApiKey::firstOrFail();
        $plainTextToken = session('new_api_token');

        $this->assertSame($user->id, $apiKey->user_id);
        $this->assertSame('Local dashboard', $apiKey->name);
        $this->assertStringStartsWith('saints_test_', $plainTextToken);
        $this->assertSame(hash('sha256', $plainTextToken), $apiKey->token_hash);
        $this->assertSame(substr($plainTextToken, 0, 24), $apiKey->prefix);
        $this->assertSame('2026-09-01 23:59:59', $apiKey->expires_at->format('Y-m-d H:i:s'));

        $this
            ->actingAs($user)
            ->get(route('developers.api-keys.index'))
            ->assertOk()
            ->assertSee('Local dashboard')
            ->assertSee('API key created')
            ->assertDontSee($plainTextToken)
            ->assertDontSee($apiKey->prefix);

        $this
            ->actingAs($user)
            ->get(route('developers.api-keys.index'))
            ->assertOk()
            ->assertSee('Local dashboard')
            ->assertDontSee($plainTextToken)
            ->assertDontSee($apiKey->prefix);

        Carbon::setTestNow();
    }

    public function test_user_can_revoke_only_their_own_key(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $apiKey = $user->developerApiKeys()->create([
            'name' => 'Owned key',
            'prefix' => 'saints_test_owned',
            'token_hash' => hash('sha256', 'saints_test_owned'),
        ]);

        $otherApiKey = $otherUser->developerApiKeys()->create([
            'name' => 'Other key',
            'prefix' => 'saints_test_other',
            'token_hash' => hash('sha256', 'saints_test_other'),
        ]);

        $this
            ->actingAs($user)
            ->delete(route('developers.api-keys.destroy', $otherApiKey))
            ->assertNotFound();

        $this->assertNull($otherApiKey->fresh()->revoked_at);

        $this
            ->actingAs($user)
            ->delete(route('developers.api-keys.destroy', $apiKey))
            ->assertRedirect(route('developers.api-keys.index'));

        $this->assertNotNull($apiKey->fresh()->revoked_at);
    }

    public function test_user_can_create_more_than_three_active_keys(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $user->developerApiKeys()->create([
                'name' => "Key {$i}",
                'prefix' => "saints_test_{$i}",
                'token_hash' => hash('sha256', "saints_test_{$i}"),
            ]);
        }

        $this
            ->actingAs($user)
            ->from(route('developers.api-keys.index'))
            ->post(route('developers.api-keys.store'), [
                'name' => 'Fourth key',
            ])
            ->assertRedirect(route('developers.api-keys.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $user->developerApiKeys()->count());
    }

    public function test_expired_and_revoked_keys_do_not_affect_key_creation(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $user->developerApiKeys()->create([
                'name' => "Key {$i}",
                'prefix' => "saints_test_active_{$i}",
                'token_hash' => hash('sha256', "saints_test_active_{$i}"),
            ]);
        }

        $user->developerApiKeys()->create([
            'name' => 'Expired key',
            'prefix' => 'saints_test_expired',
            'token_hash' => hash('sha256', 'saints_test_expired'),
            'expires_at' => now()->subDay(),
        ]);

        $user->developerApiKeys()->create([
            'name' => 'Revoked key',
            'prefix' => 'saints_test_revoked',
            'token_hash' => hash('sha256', 'saints_test_revoked'),
            'revoked_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('developers.api-keys.store'), [
                'name' => 'Another active key',
            ])
            ->assertRedirect(route('developers.api-keys.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $user->developerApiKeys()->count());
    }

    public function test_laravel_api_routes_are_not_registered(): void
    {
        $this
            ->getJson('/api/saints')
            ->assertNotFound();
    }
}
