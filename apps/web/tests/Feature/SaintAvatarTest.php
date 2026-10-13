<?php

namespace Tests\Feature;

use App\Models\Saint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaintAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_uses_saved_avatar_and_profile_retains_original_portrait(): void
    {
        $saint = Saint::create([
            'primary_name' => 'St. Avatar Example',
            'slug' => 'st-avatar-example',
            'image_avatar_url' => 'https://example.com/avatar.png',
            'image_thumb_url' => 'https://example.com/thumb.webp',
            'image_portrait_url' => 'https://example.com/portrait.webp',
        ]);

        $this->get('/search?q=Avatar')
            ->assertOk()
            ->assertSee('https://example.com/avatar.png')
            ->assertSee('search-result-image--avatar')
            ->assertDontSee('https://example.com/thumb.webp');
        $this->get('/saints/st-avatar-example')
            ->assertOk()
            ->assertSee('https://example.com/portrait.webp');
        $this->assertSame('https://example.com/portrait.webp', $saint->fresh()->image_portrait_url);
        $this->assertSame('https://example.com/thumb.webp', $saint->fresh()->image_thumb_url);
    }
}
