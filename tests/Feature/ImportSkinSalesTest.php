<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\LoadsCSFloatFixtures;
use Tests\TestCase;

class ImportSkinSalesTest extends TestCase
{
    use LoadsCSFloatFixtures;

    private function fakeCSFloat(): void
    {
        $sales = collect($this->csfloatSalesFixture())
            ->groupBy(fn (array $sale) => $sale['item']['market_hash_name'])
            ->map(fn ($group) => $group->values()->all())
            ->all();

        Http::fake(function (Request $request) use ($sales) {
            foreach ($sales as $skin => $payload) {
                if (str_contains($request->url(), rawurlencode($skin))) {
                    return Http::response($payload, 200);
                }
            }

            return Http::response([], 404);
        });
    }

    private function track(string $name, ?float $min = null, ?float $max = null): TrackedSkin
    {
        return TrackedSkin::create([
            'market_hash_name' => $name,
            'min_float' => $min,
            'max_float' => $max,
            'enabled' => true,
        ]);
    }

    #[Test]
    public function it_shows_an_import_button_for_each_tracked_skin(): void
    {
        $skin = $this->track('AK-47 | Redline (Field-Tested)');

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee(route('skins.import', $skin), false)
            ->assertSee('js-import', false)
            ->assertSee('js-import-status', false)
            ->assertSee('data-url', false);
    }

    #[Test]
    public function it_imports_only_the_requested_skin(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');
        $this->track('AWP | Dragon Lore (Factory New)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 2, 'skipped' => 0]);

        $this->assertSame(2, Sale::count());
        $this->assertSame(0, Sale::where('market_hash_name', 'AWP | Dragon Lore (Factory New)')->count());
    }

    #[Test]
    public function it_reports_imported_and_skipped_counts(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 2,
                'skipped' => 0,
                'skipped_duplicates' => 0,
                'skipped_out_of_range' => 0,
                'skipped_malformed' => 0,
            ]);
    }

    #[Test]
    public function it_ignores_duplicate_sale_ids_on_a_second_run(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 2, 'skipped' => 0]);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 2,
                'skipped_duplicates' => 2,
                'skipped_out_of_range' => 0,
            ]);

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_ignores_sale_ids_already_stored_for_that_skin(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $entry = collect($this->csfloatSalesFixture())
            ->firstWhere('item.market_hash_name', 'AK-47 | Redline (Field-Tested)');

        Sale::create([
            'sale_id' => $entry['id'],
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => 33.61,
            'float_value' => 0.21882218,
            'sold_at' => $entry['sold_at'],
            'paint_index' => 282,
            'raw_json' => $entry,
        ]);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped' => 1, 'skipped_duplicates' => 1]);

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_imports_everything_when_no_float_range_is_configured(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 2, 'skipped' => 0, 'skipped_out_of_range' => 0]);

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_applies_the_min_float_bound(): void
    {
        // AK fixtures: 0.21882218 and 0.31110835
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.25);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped' => 1,
                'skipped_out_of_range' => 1,
                'skipped_duplicates' => 0,
            ]);

        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(0.31110835, (float) Sale::sole()->float_value, 0.0000001);
    }

    #[Test]
    public function it_applies_the_max_float_bound(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', max: 0.25);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped' => 1,
                'skipped_out_of_range' => 1,
            ]);

        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(0.21882218, (float) Sale::sole()->float_value, 0.0000001);
    }

    #[Test]
    public function it_applies_both_float_bounds(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.25, max: 0.4);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped' => 1, 'skipped_out_of_range' => 1]);

        $this->assertSame(1, Sale::count());
    }

    #[Test]
    public function it_imports_nothing_when_every_sale_is_out_of_range(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.9);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 2,
                'skipped_out_of_range' => 2,
                'skipped_duplicates' => 0,
            ]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_treats_a_boundary_float_as_included(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.21882218);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 2, 'skipped' => 0, 'skipped_out_of_range' => 0]);
    }

    #[Test]
    public function it_rejects_a_floatless_sale_when_a_range_is_configured(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response([
                [
                    'id' => '5000',
                    'price' => 1500,
                    'sold_at' => '2026-01-01T00:00:00Z',
                    'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'],
                ],
            ]),
        ]);
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.1);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 0, 'skipped_out_of_range' => 1]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_accepts_a_floatless_sale_when_no_range_is_configured(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response([
                [
                    'id' => '5001',
                    'price' => 1500,
                    'sold_at' => '2026-01-01T00:00:00Z',
                    'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'],
                ],
            ]),
        ]);
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped_out_of_range' => 0]);

        $this->assertSame(1, Sale::count());
    }

    #[Test]
    public function it_counts_malformed_entries_as_skipped(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response([
                ['price' => 1500, 'item' => []],
                [
                    'id' => '5002',
                    'price' => 1500,
                    'sold_at' => '2026-01-01T00:00:00Z',
                    'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)'],
                ],
            ]),
        ]);
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped' => 1,
                'skipped_malformed' => 1,
            ]);
    }

    #[Test]
    public function it_works_for_a_disabled_skin_on_manual_request(): void
    {
        $this->fakeCSFloat();
        $ak = TrackedSkin::create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'enabled' => false,
        ]);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson(['imported' => 2]);

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_does_not_touch_other_tracked_skins(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');
        $this->track('AWP | Dragon Lore (Factory New)');

        $this->post(route('skins.import', $ak))->assertOk();

        $this->assertSame(2, Sale::count());
        $this->assertSame(1, Http::recorded()->count());
        $this->assertTrue(Http::recorded()->last()[0]->url() !== '');
        $this->assertStringContainsString('AK-47', rawurldecode(Http::recorded()->last()[0]->url()));
    }

    #[Test]
    public function it_returns_a_gateway_error_when_csfloat_fails(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response(['error' => 'unauthorized'], 403),
        ]);
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertStatus(502)
            ->assertJsonStructure(['message']);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_persists_the_complete_raw_json(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))->assertOk();

        $entry = collect($this->csfloatSalesFixture())
            ->firstWhere('item.market_hash_name', 'AK-47 | Redline (Field-Tested)');

        $this->assertSame($entry, Sale::where('sale_id', $entry['id'])->sole()->raw_json);
    }
}
