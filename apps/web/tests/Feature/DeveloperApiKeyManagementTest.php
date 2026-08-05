<?php

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\Saint;
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
            ->assertSee($plainTextToken);

        $this
            ->actingAs($user)
            ->get(route('developers.api-keys.index'))
            ->assertOk()
            ->assertSee('Local dashboard')
            ->assertDontSee($plainTextToken);

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

    public function test_user_can_have_only_three_active_keys(): void
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
                'name' => 'One too many',
            ])
            ->assertRedirect(route('developers.api-keys.index'))
            ->assertSessionHasErrors('name');

        $this->assertSame(3, $user->developerApiKeys()->count());
    }

    public function test_expired_and_revoked_keys_do_not_count_against_active_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 2; $i++) {
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
                'name' => 'Tenth active key',
            ])
            ->assertRedirect(route('developers.api-keys.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $user->developerApiKeys()->count());
    }

    public function test_api_routes_require_a_developer_api_key(): void
    {
        $this
            ->getJson('/api/saints')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_api_routes_reject_invalid_developer_api_key(): void
    {
        $this
            ->withHeader('Authorization', 'Bearer saints_test_invalid')
            ->getJson('/api/saints')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'API key is invalid or inactive.',
            ]);
    }

    public function test_api_routes_reject_revoked_and_expired_developer_api_keys(): void
    {
        $user = User::factory()->create();
        $revokedToken = 'saints_test_revoked_token';
        $expiredToken = 'saints_test_expired_token';

        $user->developerApiKeys()->create([
            'name' => 'Revoked key',
            'prefix' => substr($revokedToken, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($revokedToken),
            'revoked_at' => now(),
        ]);

        $user->developerApiKeys()->create([
            'name' => 'Expired key',
            'prefix' => substr($expiredToken, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($expiredToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this
            ->withHeader('Authorization', "Bearer {$revokedToken}")
            ->getJson('/api/saints')
            ->assertUnauthorized();

        $this
            ->withHeader('Authorization', "Bearer {$expiredToken}")
            ->getJson('/api/saints')
            ->assertUnauthorized();
    }

    public function test_api_routes_accept_bearer_developer_api_key_and_mark_it_used(): void
    {
        Carbon::setTestNow('2026-08-04 11:00:00');
        $user = User::factory()->create();
        $token = 'saints_test_valid_token';
        $apiKey = $user->developerApiKeys()->create([
            'name' => 'Valid key',
            'prefix' => substr($token, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($token),
        ]);

        Saint::create([
            'primary_name' => 'Saint API Example',
            'slug' => 'saint-api-example',
        ]);

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/saints')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'API Example');

        $this->assertSame(
            '2026-08-04 11:00:00',
            $apiKey->fresh()->last_used_at->format('Y-m-d H:i:s'),
        );

        Carbon::setTestNow();
    }

    public function test_api_routes_accept_x_api_key_header(): void
    {
        $user = User::factory()->create();
        $token = 'saints_test_header_token';

        $user->developerApiKeys()->create([
            'name' => 'Header key',
            'prefix' => substr($token, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($token),
        ]);

        $this
            ->withHeader('X-API-Key', $token)
            ->getJson('/api/saints')
            ->assertOk();
    }

    public function test_api_routes_throttle_each_developer_api_key_to_ten_requests_per_second(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();
        $token = 'saints_test_rate_limited_token';

        $user->developerApiKeys()->create([
            'name' => 'Rate limited key',
            'prefix' => substr($token, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($token),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this
                ->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/saints')
                ->assertOk();
        }

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/saints')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '1')
            ->assertJson([
                'message' => 'Too many requests for this API key.',
            ]);

        Carbon::setTestNow('2026-08-05 12:00:01');

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/saints')
            ->assertOk();

        Carbon::setTestNow();
    }
}
