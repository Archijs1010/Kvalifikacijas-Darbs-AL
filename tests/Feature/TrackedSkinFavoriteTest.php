<?php

namespace Tests\Feature;

use App\Models\TrackedSkin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackedSkinFavoriteTest extends TestCase
{
    #[Test]
    public function it_marks_a_skin_as_a_favorite(): void
    {
        $skin = TrackedSkin::factory()->create(['is_favorite' => false]);

        $this->patch(route('skins.favorite', $skin))
            ->assertRedirect(route('skins.index'))
            ->assertSessionHas('status');

        $this->assertTrue($skin->fresh()->is_favorite);
    }

    #[Test]
    public function it_unmarks_a_favorite(): void
    {
        $skin = TrackedSkin::factory()->create(['is_favorite' => true]);

        $this->patch(route('skins.favorite', $skin))
            ->assertRedirect(route('skins.index'));

        $this->assertFalse($skin->fresh()->is_favorite);
    }

    #[Test]
    public function it_lists_favorites_first(): void
    {
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AWP | Asiimov (Field-Tested)',
            'is_favorite' => false,
        ]);
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'is_favorite' => true,
        ]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'AK-47 | Redline (Field-Tested)',
                'AWP | Asiimov (Field-Tested)',
            ]);
    }

    #[Test]
    public function it_orders_favorites_and_non_favorites_alphabetically_within_their_group(): void
    {
        TrackedSkin::factory()->create(['market_hash_name' => 'B | Skin (Field-Tested)', 'is_favorite' => true]);
        TrackedSkin::factory()->create(['market_hash_name' => 'A | Skin (Field-Tested)', 'is_favorite' => true]);
        TrackedSkin::factory()->create(['market_hash_name' => 'D | Skin (Field-Tested)', 'is_favorite' => false]);
        TrackedSkin::factory()->create(['market_hash_name' => 'C | Skin (Field-Tested)', 'is_favorite' => false]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'A | Skin (Field-Tested)',
                'B | Skin (Field-Tested)',
                'C | Skin (Field-Tested)',
                'D | Skin (Field-Tested)',
            ]);
    }

    #[Test]
    public function it_filters_to_favorites_only(): void
    {
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AWP | Asiimov (Field-Tested)',
            'is_favorite' => false,
        ]);
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'is_favorite' => true,
        ]);

        $this->get(route('skins.index', ['favorites' => 1]))
            ->assertOk()
            ->assertSee('AK-47 | Redline (Field-Tested)')
            ->assertDontSee('AWP | Asiimov (Field-Tested)');
    }

    #[Test]
    public function it_shows_every_skin_when_the_filter_is_absent(): void
    {
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AWP | Asiimov (Field-Tested)',
            'is_favorite' => false,
        ]);
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'is_favorite' => true,
        ]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('AK-47 | Redline (Field-Tested)')
            ->assertSee('AWP | Asiimov (Field-Tested)');
    }

    #[Test]
    public function it_shows_the_empty_state_when_there_are_no_favorites(): void
    {
        TrackedSkin::factory()->create([
            'market_hash_name' => 'AWP | Asiimov (Field-Tested)',
            'is_favorite' => false,
        ]);

        $this->get(route('skins.index', ['favorites' => 1]))
            ->assertOk()
            ->assertSee('No tracked skins yet.')
            ->assertDontSee('AWP | Asiimov (Field-Tested)');
    }

    #[Test]
    public function it_renders_a_favorite_button_for_each_skin(): void
    {
        $skin = TrackedSkin::factory()->create();

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee(route('skins.favorite', $skin), false);
    }

    #[Test]
    public function it_renders_a_filled_star_for_favorites(): void
    {
        TrackedSkin::factory()->create(['is_favorite' => true]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('title="Unpin"', false)
            ->assertSee('★', false);
    }

    #[Test]
    public function it_renders_an_outline_star_for_non_favorites(): void
    {
        TrackedSkin::factory()->create(['is_favorite' => false]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('title="Pin"', false)
            ->assertSee('☆', false);
    }

    #[Test]
    public function it_renders_the_favorites_only_filter(): void
    {
        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('Favorites only')
            ->assertSee('name="favorites"', false);
    }

    #[Test]
    public function it_checks_the_filter_when_it_is_active(): void
    {
        $this->get(route('skins.index', ['favorites' => 1]))
            ->assertOk()
            ->assertSee('name="favorites" value="1" checked', false);
    }

    #[Test]
    public function it_does_not_touch_other_fields_when_favoriting(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 0.15,
            'max_float' => 0.35,
            'enabled' => false,
            'is_favorite' => false,
        ]);

        $this->patch(route('skins.favorite', $skin));

        $fresh = $skin->fresh();

        $this->assertTrue($fresh->is_favorite);
        $this->assertFalse($fresh->enabled);
        $this->assertSame('0.1500', $fresh->min_float);
        $this->assertSame('0.3500', $fresh->max_float);
        $this->assertSame('AK-47 | Redline (Field-Tested)', $fresh->market_hash_name);
    }

    #[Test]
    public function it_preserves_the_favorites_filter_when_toggling(): void
    {
        $skin = TrackedSkin::factory()->create(['is_favorite' => false]);

        $this->patch(route('skins.favorite', $skin).'?favorites=1')
            ->assertRedirect(route('skins.index', ['favorites' => 1]));

        $this->assertTrue($skin->fresh()->is_favorite);
    }

    #[Test]
    public function it_defaults_new_skins_to_not_favorite(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ])->assertRedirect(route('skins.index'));

        $this->assertFalse(TrackedSkin::sole()->is_favorite);
    }

    #[Test]
    public function it_does_not_change_favorites_on_a_regular_update(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'is_favorite' => true,
        ]);

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => 'AK-47 | Redline (Minimal Wear)',
        ])->assertRedirect(route('skins.index'));

        $this->assertTrue($skin->fresh()->is_favorite);
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_skin(): void
    {
        $this->patch('/skins/9999/favorite')->assertNotFound();
    }
}
