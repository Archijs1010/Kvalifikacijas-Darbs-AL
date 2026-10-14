<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackedSkinNoteTest extends TestCase
{
    #[Test]
    public function it_creates_a_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->put(route('skins.note.update', $skin), [
            'note' => 'Bought cheap, hold until next case.',
        ])
            ->assertRedirect(route('skins.index'))
            ->assertSessionHas('status');

        $this->assertSame('Bought cheap, hold until next case.', $skin->fresh()->note);
    }

    #[Test]
    public function it_updates_a_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Old note']);

        $this->put(route('skins.note.update', $skin), [
            'note' => 'New note',
        ])->assertRedirect(route('skins.index'));

        $this->assertSame('New note', $skin->fresh()->note);
    }

    #[Test]
    public function it_deletes_a_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Temporary']);

        $this->delete(route('skins.note.destroy', $skin))
            ->assertRedirect(route('skins.index'))
            ->assertSessionHas('status');

        $this->assertNull($skin->fresh()->note);
    }

    #[Test]
    public function it_deletes_only_the_note_and_keeps_the_skin(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'note' => 'Temporary',
        ]);

        $this->delete(route('skins.note.destroy', $skin));

        $this->assertDatabaseHas('tracked_skins', [
            'id' => $skin->id,
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);
    }

    #[Test]
    public function it_trims_whitespace_around_the_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->put(route('skins.note.update', $skin), [
            'note' => "  spaced note  \n",
        ])->assertRedirect(route('skins.index'))->assertSessionHasNoErrors();

        $this->assertSame('spaced note', $skin->fresh()->note);
    }

    #[Test]
    public function it_treats_a_blank_note_as_null(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Existing']);

        $this->put(route('skins.note.update', $skin), [
            'note' => '   ',
        ])->assertRedirect(route('skins.index'))->assertSessionHasNoErrors();

        $this->assertNull($skin->fresh()->note);
    }

    #[Test]
    public function it_treats_a_missing_note_as_null(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Existing']);

        $this->put(route('skins.note.update', $skin))
            ->assertRedirect(route('skins.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull($skin->fresh()->note);
    }

    #[Test]
    public function it_keeps_a_note_of_exactly_the_maximum_length(): void
    {
        $skin = TrackedSkin::factory()->create();
        $note = str_repeat('a', 1000);

        $this->put(route('skins.note.update', $skin), ['note' => $note])
            ->assertSessionHasNoErrors();

        $this->assertSame(1000, mb_strlen($skin->fresh()->note));
    }

    #[Test]
    public function it_rejects_an_overlong_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->put(route('skins.note.update', $skin), [
            'note' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('note');

        $this->assertNull($skin->fresh()->note);
    }

    #[Test]
    public function it_rejects_a_non_string_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->put(route('skins.note.update', $skin), [
            'note' => ['not' => 'a string'],
        ])->assertSessionHasErrors('note');

        $this->assertNull($skin->fresh()->note);
    }

    #[Test]
    public function it_stores_the_note_in_the_database(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->put(route('skins.note.update', $skin), ['note' => 'Persist me']);

        $this->assertDatabaseHas('tracked_skins', [
            'id' => $skin->id,
            'note' => 'Persist me',
        ]);
    }

    #[Test]
    public function it_prefills_the_note_on_the_edit_form(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Prefilled note']);

        $this->get(route('skins.note.edit', $skin))
            ->assertOk()
            ->assertSee('Prefilled note');
    }

    #[Test]
    public function it_shows_the_note_page(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'note' => null,
        ]);

        $this->get(route('skins.note.edit', $skin))
            ->assertOk()
            ->assertSee('AK-47 | Redline (Field-Tested)')
            ->assertSee('name="note"', false);
    }

    #[Test]
    public function it_offers_a_delete_action_only_when_a_note_exists(): void
    {
        $without = TrackedSkin::factory()->create(['note' => null]);
        $with = TrackedSkin::factory()->create(['note' => 'Something']);

        $this->get(route('skins.note.edit', $without))
            ->assertOk()
            ->assertDontSee('Delete note');

        $this->get(route('skins.note.edit', $with))
            ->assertOk()
            ->assertSee('Delete note');
    }

    #[Test]
    public function it_shows_the_note_on_the_tracked_skins_list(): void
    {
        TrackedSkin::factory()->create(['note' => 'Watch the float']);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('Watch the float');
    }

    #[Test]
    public function it_shows_an_add_note_link_when_there_is_no_note(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => null]);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee(route('skins.note.edit', $skin), false)
            ->assertSee('Add note');
    }

    #[Test]
    public function it_shows_an_edit_note_link_when_a_note_exists(): void
    {
        $skin = TrackedSkin::factory()->create(['note' => 'Something']);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee(route('skins.note.edit', $skin), false)
            ->assertSee('Edit note');
    }

    #[Test]
    public function it_escapes_html_in_the_note(): void
    {
        TrackedSkin::factory()->create(['note' => '<script>alert(1)</script>']);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function it_does_not_touch_other_fields_when_saving_a_note(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'min_float' => 0.15,
            'max_float' => 0.35,
            'enabled' => false,
            'is_favorite' => true,
            'note' => null,
        ]);

        $this->put(route('skins.note.update', $skin), ['note' => 'A note']);

        $fresh = $skin->fresh();

        $this->assertSame('A note', $fresh->note);
        $this->assertSame('AK-47 | Redline (Field-Tested)', $fresh->market_hash_name);
        $this->assertSame('0.1500', $fresh->min_float);
        $this->assertSame('0.3500', $fresh->max_float);
        $this->assertFalse($fresh->enabled);
        $this->assertTrue($fresh->is_favorite);
    }

    #[Test]
    public function it_does_not_create_sales_when_saving_a_note(): void
    {
        $skin = TrackedSkin::factory()->create();

        $this->put(route('skins.note.update', $skin), ['note' => 'A note']);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_does_not_change_the_note_when_updating_the_skin(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'note' => 'Keep me',
        ]);

        $this->put(route('skins.update', $skin), [
            'market_hash_name' => 'AK-47 | Redline (Minimal Wear)',
        ])->assertRedirect(route('skins.index'));

        $this->assertSame('Keep me', $skin->fresh()->note);
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_skin(): void
    {
        $this->get('/skins/9999/note')->assertNotFound();
        $this->put('/skins/9999/note', ['note' => 'x'])->assertNotFound();
        $this->delete('/skins/9999/note')->assertNotFound();
    }
}
