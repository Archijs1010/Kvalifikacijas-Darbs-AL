<?php

namespace Tests\Feature;

use App\Models\TrackedSkin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackedSkinCrudTest extends TestCase
{
    #[Test]
    public function it_lists_tracked_skins(): void
    {
        TrackedSkin::factory()->create(['market_hash_name' => 'AWP | Asiimov (Field-Tested)']);
        TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'AK-47 | Redline (Field-Tested)',
                'AWP | Asiimov (Field-Tested)',
            ]);
    }

    #[Test]
    public function it_creates_a_tracked_skin(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.1000',
            'max_float' => '0.3000',
        ])
            ->assertRedirect(route('skins.index'))
            ->assertSessionHas('status');

        $skin = TrackedSkin::sole();

        $this->assertSame('AK-47 | Redline (Field-Tested)', $skin->market_hash_name);
        $this->assertSame('0.1000', $skin->min_float);
        $this->assertSame('0.3000', $skin->max_float);
        $this->assertTrue($skin->enabled);
    }

    #[Test]
    public function it_trims_the_market_hash_name(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => '  AK-47 | Redline (Field-Tested)  ',
        ])->assertRedirect(route('skins.index'));

        $this->assertSame('AK-47 | Redline (Field-Tested)', TrackedSkin::sole()->market_hash_name);
    }

    #[Test]
    public function it_creates_a_tracked_skin_without_float_bounds(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ])->assertRedirect(route('skins.index'));

        $skin = TrackedSkin::sole();

        $this->assertNull($skin->min_float);
        $this->assertNull($skin->max_float);
    }

    #[Test]
    public function it_treats_blank_float_inputs_as_null(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '',
            'max_float' => '  ',
        ])->assertRedirect(route('skins.index'));

        $skin = TrackedSkin::sole();

        $this->assertNull($skin->min_float);
        $this->assertNull($skin->max_float);
    }

    #[Test]
    public function it_shows_the_edit_form_prefilled(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 0.15,
            'max_float' => 0.35,
            'enabled' => false,
        ]);

        $this->get(route('skins.edit', $skin))
            ->assertOk()
            ->assertSee('AK-47 | Redline (Field-Tested)')
            ->assertSee('0.1500', false)
            ->assertSee('0.3500', false);
    }

    #[Test]
    public function it_updates_a_tracked_skin(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 0.15,
        ]);

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => 'AK-47 | Redline (Minimal Wear)',
            'min_float' => '0.0100',
            'max_float' => '0.0700',
            'enabled' => '1',
        ])->assertRedirect(route('skins.index'));

        $skin->refresh();

        $this->assertSame('AK-47 | Redline (Minimal Wear)', $skin->market_hash_name);
        $this->assertSame('0.0100', $skin->min_float);
        $this->assertSame('0.0700', $skin->max_float);
        $this->assertTrue($skin->enabled);
    }

    #[Test]
    public function it_can_unset_the_float_bounds_on_update(): void
    {
        $skin = TrackedSkin::factory()->create(['min_float' => 0.15, 'max_float' => 0.35]);

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => $skin->market_hash_name,
            'min_float' => '',
            'max_float' => '',
        ])->assertRedirect(route('skins.index'));

        $this->assertNull($skin->fresh()->min_float);
        $this->assertNull($skin->fresh()->max_float);
    }

    #[Test]
    public function it_allows_saving_a_skin_under_its_own_name(): void
    {
        $skin = TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.2000',
        ])->assertRedirect(route('skins.index'))->assertSessionHasNoErrors();

        $this->assertSame('0.2000', $skin->fresh()->min_float);
    }

    #[Test]
    public function it_deletes_a_tracked_skin(): void
    {
        $skin = TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);

        $this->delete(route('skins.destroy', $skin))
            ->assertRedirect(route('skins.index'));

        $this->assertDatabaseMissing('tracked_skins', ['id' => $skin->id]);
    }

    #[Test]
    public function it_disables_an_enabled_skin(): void
    {
        $skin = TrackedSkin::factory()->create(['enabled' => true]);

        $this->patch(route('skins.toggle', $skin))->assertRedirect(route('skins.index'));

        $this->assertFalse($skin->fresh()->enabled);
    }

    #[Test]
    public function it_enables_a_disabled_skin(): void
    {
        $skin = TrackedSkin::factory()->create(['enabled' => false]);

        $this->patch(route('skins.toggle', $skin))->assertRedirect(route('skins.index'));

        $this->assertTrue($skin->fresh()->enabled);
    }

    #[Test]
    public function it_toggles_without_touching_the_float_bounds(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 0.15,
            'max_float' => 0.35,
        ]);

        $this->patch(route('skins.toggle', $skin));

        $fresh = $skin->fresh();

        $this->assertFalse($fresh->enabled);
        $this->assertSame('0.1500', $fresh->min_float);
        $this->assertSame('0.3500', $fresh->max_float);
        $this->assertSame('AK-47 | Redline (Field-Tested)', $fresh->market_hash_name);
    }

    #[Test]
    public function it_requires_a_market_hash_name(): void
    {
        $this->post(route('skins.store'), ['market_hash_name' => ''])
            ->assertSessionHasErrors('market_hash_name');

        $this->post(route('skins.store'), ['market_hash_name' => '   '])
            ->assertSessionHasErrors('market_hash_name');

        $this->assertSame(0, TrackedSkin::count());
    }

    #[Test]
    public function it_rejects_duplicate_market_hash_names(): void
    {
        TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);

        $this->post(route('skins.store'), ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'])
            ->assertSessionHasErrors('market_hash_name');

        $this->assertSame(1, TrackedSkin::count());
    }

    #[Test]
    public function it_rejects_renaming_a_skin_onto_an_existing_one(): void
    {
        TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);
        $other = TrackedSkin::factory()->create(['market_hash_name' => 'AWP | Asiimov (Field-Tested)']);

        $this->put(route('skins.update', $other), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ])->assertSessionHasErrors('market_hash_name');

        $this->assertSame('AWP | Asiimov (Field-Tested)', $other->fresh()->market_hash_name);
    }

    #[Test]
    public function it_allows_reusing_the_name_of_a_deleted_skin(): void
    {
        TrackedSkin::factory()->create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);

        $this->post(route('skins.store'), ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'])
            ->assertSessionHasErrors('market_hash_name');

        $this->delete(route('skins.destroy', TrackedSkin::sole()));

        $this->post(route('skins.store'), ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TrackedSkin::count());
    }

    #[Test]
    public function it_rejects_float_bounds_outside_zero_to_one(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '-0.1',
        ])->assertSessionHasErrors('min_float');

        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'max_float' => '1.5',
        ])->assertSessionHasErrors('max_float');

        $this->assertSame(0, TrackedSkin::count());
    }

    #[Test]
    public function it_accepts_the_float_bounds_zero_and_one(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0',
            'max_float' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TrackedSkin::count());
    }

    #[Test]
    public function it_rejects_non_numeric_float_bounds(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 'low',
        ])->assertSessionHasErrors('min_float');

        $this->assertSame(0, TrackedSkin::count());
    }

    #[Test]
    public function it_rejects_a_min_float_greater_than_the_max_float(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.8000',
            'max_float' => '0.2000',
        ])->assertSessionHasErrors([
            'min_float' => 'Min float cannot be greater than max float.',
        ]);

        $this->assertSame(0, TrackedSkin::count());
    }

    #[Test]
    public function it_flags_the_ordering_issue_only_on_the_min_float_field(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.8000',
            'max_float' => '0.2000',
        ])->assertSessionHasErrors('min_float');

        $errors = session('errors')->getBag('default');

        $this->assertTrue($errors->has('min_float'));
        $this->assertFalse($errors->has('max_float'));
    }

    #[Test]
    public function it_does_not_flag_ordering_when_only_one_bound_is_given(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.9000',
        ])->assertSessionHasNoErrors();

        $this->post(route('skins.store'), [
            'market_hash_name' => 'AWP | Asiimov (Field-Tested)',
            'max_float' => '0.1000',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, TrackedSkin::count());
    }

    #[Test]
    public function it_still_reports_the_range_error_when_the_order_is_reversed_and_out_of_range(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '1.5',
            'max_float' => '0.2000',
        ])->assertSessionHasErrors(['min_float']);

        $this->assertSame(0, TrackedSkin::count());
    }

    #[Test]
    public function it_accepts_equal_min_and_max_floats(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.2500',
            'max_float' => '0.2500',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TrackedSkin::count());
    }

    #[Test]
    public function it_validates_the_float_order_on_update_too(): void
    {
        $skin = TrackedSkin::factory()->create();

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => $skin->market_hash_name,
            'min_float' => '0.9000',
            'max_float' => '0.1000',
        ])->assertSessionHasErrors('min_float');
    }

    #[Test]
    public function it_allows_only_a_min_float_bound(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => '0.1000',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_allows_only_a_max_float_bound(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'max_float' => '0.1000',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_skin(): void
    {
        $this->get('/skins/9999/edit')->assertNotFound();
        $this->put('/skins/9999', ['market_hash_name' => 'x'])->assertNotFound();
        $this->patch('/skins/9999/toggle')->assertNotFound();
        $this->delete('/skins/9999')->assertNotFound();
    }

    #[Test]
    public function it_saves_a_phase_alongside_the_name(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
            'min_float' => '0.0000',
            'max_float' => '0.0700',
            'phase' => 'Phase 4',
            'enabled' => '1',
        ])->assertRedirect(route('skins.index'))->assertSessionHasNoErrors();

        $skin = TrackedSkin::sole();

        $this->assertSame('Phase 4', $skin->phase);
        $this->assertSame('0.0000', $skin->min_float);
    }

    #[Test]
    public function it_leaves_the_phase_null_when_the_field_is_blank(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'phase' => '   ',
        ])->assertSessionHasNoErrors();

        $this->assertNull(TrackedSkin::sole()->phase);
    }

    #[Test]
    public function it_leaves_the_phase_null_when_the_field_is_absent(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ])->assertSessionHasNoErrors();

        $this->assertNull(TrackedSkin::sole()->phase);
    }

    #[Test]
    public function it_can_unset_a_phase_on_update(): void
    {
        $skin = TrackedSkin::factory()->create(['market_hash_name' => '★ Karambit | Doppler (Factory New)']);
        $skin->forceFill(['phase' => 'Phase 4'])->save();

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => $skin->market_hash_name,
            'phase' => '',
        ])->assertRedirect(route('skins.index'))->assertSessionHasNoErrors();

        $this->assertNull($skin->fresh()->phase);
    }

    #[Test]
    public function it_shows_the_phase_on_the_tracked_skins_table(): void
    {
        TrackedSkin::factory()->create([
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
        ])->forceFill(['phase' => 'Phase 4'])->save();

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('Phase 4</td>', false);
    }

    #[Test]
    public function it_prefills_the_phase_on_the_edit_form(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
        ]);
        $skin->forceFill(['phase' => 'Sapphire'])->save();

        $this->get(route('skins.edit', $skin))
            ->assertOk()
            ->assertSee('value="Sapphire"', false);
    }

    #[Test]
    public function it_rejects_an_overlong_phase(): void
    {
        $this->post(route('skins.store'), [
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
            'phase' => str_repeat('a', 51),
        ])->assertSessionHasErrors('phase');
    }

    #[Test]
    public function it_offers_phase_suggestions_on_both_forms(): void
    {
        $index = $this->get(route('skins.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="phase-options"', $index);

        $skin = TrackedSkin::factory()->create();
        $edit = $this->get(route('skins.edit', $skin))->assertOk()->getContent();
        $this->assertStringContainsString('id="phase-options"', $edit);
        $this->assertStringContainsString('list="phase-options"', $edit);
    }
}
