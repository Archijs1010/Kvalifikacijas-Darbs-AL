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

    private function track(string $name, ?float $min = null, ?float $max = null, ?string $phase = null): TrackedSkin
    {
        return TrackedSkin::create([
            'market_hash_name' => $name,
            'min_float' => $min,
            'max_float' => $max,
            'phase' => $phase,
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

    #[Test]
    public function it_flags_an_unknown_name_instead_of_reporting_a_silent_zero(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200)]);
        $ak = $this->track('★ AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 0,
                'no_sales' => true,
            ]);
    }

    #[Test]
    public function it_does_not_flag_no_sales_when_a_range_filters_everything_out(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)', min: 0.99);

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 2,
                'skipped_out_of_range' => 2,
                'no_sales' => false,
            ]);
    }

    #[Test]
    public function it_does_not_flag_no_sales_when_everything_is_a_duplicate(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))->assertOk();
        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 2,
                'no_sales' => false,
            ]);
    }

    #[Test]
    public function it_does_not_flag_no_sales_when_the_request_fails_outright(): void
    {
        Http::fake(['csfloat.com/*' => Http::response('boom', 500)]);
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))->assertStatus(502);
    }

    #[Test]
    public function it_round_trips_a_starred_name_from_the_form_to_the_api(): void
    {
        $name = '★ AK-47 | Redline (Field-Tested)';

        $this->post(route('skins.store'), [
            'market_hash_name' => $name,
            'min_float' => '',
            'max_float' => '',
            'enabled' => '1',
        ])->assertRedirect(route('skins.index'));

        $skin = TrackedSkin::sole();
        $this->assertSame($name, $skin->market_hash_name);

        Http::fake(function (Request $request) use ($name) {
            $this->assertStringContainsString(rawurlencode($name), $request->url());

            return Http::response([], 200);
        });

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson(['no_sales' => true]);
    }

    #[Test]
    public function it_explains_how_to_write_a_correct_name_on_the_tracked_skins_form(): void
    {
        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('Must match the CSFloat name exactly', false)
            ->assertSee('Karambit | Doppler (Factory New)', false);
    }

    #[Test]
    public function the_import_script_reports_an_unknown_name_distinctly(): void
    {
        $html = $this->get(route('skins.index'))->assertOk()->getContent();

        $this->assertStringContainsString('No sales found for this name', $html);
        $this->assertStringContainsString('data.no_sales', $html);
    }

    #[Test]
    public function the_bulk_import_reports_which_names_had_no_sales(): void
    {
        $starred = '★ AK-47 | Redline (Field-Tested)';
        $this->track('AK-47 | Redline (Field-Tested)');
        $this->track($starred);

        $akSales = array_values(array_filter(
            $this->csfloatSalesFixture(),
            fn (array $sale) => $sale['item']['market_hash_name'] === 'AK-47 | Redline (Field-Tested)',
        ));

        $expectedUrl = 'https://csfloat.com/api/v1/history/AK-47 | Redline (Field-Tested)/sales';

        Http::fake(function (Request $request) use ($akSales, $expectedUrl) {
            return urldecode($request->url()) === $expectedUrl
                ? Http::response($akSales, 200)
                : Http::response([], 200);
        });

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 2,
                'no_sales_skins' => [$starred],
            ]);
    }

    #[Test]
    public function it_persists_the_paint_seed_so_each_instance_is_distinguishable(): void
    {
        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))->assertOk();

        $stored = Sale::pluck('paint_seed', 'sale_id');

        foreach ($this->csfloatSalesFixture() as $entry) {
            if ($entry['item']['market_hash_name'] !== 'AK-47 | Redline (Field-Tested)') {
                continue;
            }

            $this->assertSame($entry['item']['paint_seed'], $stored[$entry['id']]);
        }

        $this->assertEqualsCanonicalizing(
            [125, 620],
            Sale::pluck('paint_seed')->map(fn ($seed) => (int) $seed)->all(),
        );
        $this->assertSame([], Sale::whereNull('paint_seed')->pluck('sale_id')->all());
    }

    #[Test]
    public function it_backfills_a_missing_paint_seed_without_reimporting_the_row(): void
    {
        $entry = collect($this->csfloatSalesFixture())
            ->firstWhere('item.market_hash_name', 'AK-47 | Redline (Field-Tested)');

        Sale::create([
            'sale_id' => $entry['id'],
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => 33.61,
            'sold_at' => $entry['sold_at'],
            'raw_json' => $entry,
        ]);

        $this->assertNull(Sale::sole()->paint_seed);

        $this->fakeCSFloat();
        $ak = $this->track('AK-47 | Redline (Field-Tested)');

        $this->post(route('skins.import', $ak))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped' => 1,
                'skipped_duplicates' => 1,
            ]);

        $this->assertSame(2, Sale::count());
        $this->assertSame($entry['item']['paint_seed'], Sale::where('sale_id', $entry['id'])->sole()->paint_seed);
        $this->assertSame([], Sale::whereNull('paint_seed')->pluck('sale_id')->all());
    }

    #[Test]
    public function it_imports_only_the_requested_phase(): void
    {
        $this->fakeDoppler([
            $this->dopplerSale('1', 'Phase 1'),
            $this->dopplerSale('2', 'Phase 2'),
            $this->dopplerSale('3', 'Phase 3'),
            $this->dopplerSale('4', 'Phase 4'),
        ]);

        $skin = $this->track('★ Karambit | Doppler (Factory New)', phase: 'Phase 4');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped' => 3,
                'skipped_wrong_phase' => 3,
                'skipped_duplicates' => 0,
                'skipped_out_of_range' => 0,
            ]);

        $this->assertSame(1, Sale::count());
        $this->assertSame('Phase 4', Sale::sole()->phase);
    }

    #[Test]
    public function it_keeps_price_context_by_not_mixing_phases(): void
    {
        $this->fakeDoppler([
            $this->dopplerSale('1', 'Ruby'),
            $this->dopplerSale('2', 'Phase 1'),
            $this->dopplerSale('3', 'Sapphire'),
        ]);

        $skin = $this->track('★ Karambit | Doppler (Factory New)', phase: 'Ruby');

        $this->post(route('skins.import', $skin))->assertOk();

        $this->assertSame(['Ruby'], Sale::pluck('phase')->all());
    }

    #[Test]
    public function it_imports_every_phase_when_none_is_configured(): void
    {
        $this->fakeDoppler([
            $this->dopplerSale('1', 'Phase 1'),
            $this->dopplerSale('2', 'Sapphire'),
            $this->dopplerSale('3', 'Ruby'),
        ]);

        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson([
                'imported' => 3,
                'skipped_wrong_phase' => 0,
            ]);

        $this->assertSame(3, Sale::count());
    }

    #[Test]
    public function it_matches_the_phase_regardless_of_casing(): void
    {
        $this->fakeDoppler([$this->dopplerSale('1', 'Phase 4')]);

        $skin = $this->track('★ Karambit | Doppler (Factory New)', phase: 'phase 4');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped_wrong_phase' => 0]);
    }

    #[Test]
    public function it_rejects_a_phaseless_sale_when_a_phase_is_requested(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response([
                [
                    'id' => '1',
                    'price' => 50000,
                    'sold_at' => '2026-10-01T00:00:00Z',
                    'item' => ['market_hash_name' => '★ Sport Gloves | Vice (Field-Tested)', 'float_value' => 0.08],
                ],
            ], 200),
        ]);

        $skin = $this->track('★ Sport Gloves | Vice (Field-Tested)', phase: 'Phase 4');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 1,
                'skipped_wrong_phase' => 1,
            ]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_accepts_a_phaseless_sale_when_no_phase_is_requested(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response([
                [
                    'id' => '1',
                    'price' => 50000,
                    'sold_at' => '2026-10-01T00:00:00Z',
                    'item' => ['market_hash_name' => '★ Sport Gloves | Vice (Field-Tested)', 'float_value' => 0.08],
                ],
            ], 200),
        ]);

        $skin = $this->track('★ Sport Gloves | Vice (Field-Tested)');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped_wrong_phase' => 0]);
    }

    #[Test]
    public function it_applies_the_phase_and_float_filters_together(): void
    {
        $this->fakeDoppler([
            $this->dopplerSale('1', 'Phase 4', float: 0.01),
            $this->dopplerSale('2', 'Phase 4', float: 0.25),
            $this->dopplerSale('3', 'Phase 1', float: 0.01),
        ]);

        $skin = $this->track('★ Karambit | Doppler (Factory New)', min: 0.0, max: 0.07, phase: 'Phase 4');

        $this->post(route('skins.import', $skin))
            ->assertOk()
            ->assertJson([
                'imported' => 1,
                'skipped_wrong_phase' => 1,
                'skipped_out_of_range' => 1,
            ]);

        $this->assertEqualsWithDelta(0.01, (float) Sale::sole()->float_value, 0.0001);
    }

    #[Test]
    public function it_backfills_a_missing_phase_from_the_stored_payload(): void
    {
        Sale::create([
            'sale_id' => '9001',
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
            'price' => 1457.69,
            'sold_at' => '2026-10-01T00:00:00Z',
            'raw_json' => [
                'id' => '9001',
                'item' => ['market_hash_name' => '★ Karambit | Doppler (Factory New)', 'phase' => 'Phase 4'],
            ],
        ]);

        $this->assertNull(Sale::sole()->phase);

        $this->fakeDoppler([$this->dopplerSale('9002', 'Phase 1')]);
        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))->assertOk();

        $this->assertSame('Phase 4', Sale::where('sale_id', '9001')->sole()->phase);
        $this->assertSame('Phase 1', Sale::where('sale_id', '9002')->sole()->phase);
        $this->assertSame([], Sale::whereNull('phase')->pluck('sale_id')->all());
    }

    #[Test]
    public function the_form_offers_phase_suggestions(): void
    {
        $html = $this->get(route('skins.index'))->assertOk()->getContent();

        $this->assertStringContainsString('list="phase-options"', $html);
        $this->assertStringContainsString('value="Phase 4"', $html);
        $this->assertStringContainsString('value="Sapphire"', $html);
        $this->assertStringContainsString('name="phase"', $html);
    }

    private function fakeDoppler(array $sales): void
    {
        Http::fake(['csfloat.com/*' => Http::response($sales, 200)]);
    }

    private function dopplerSale(string $id, string $phase, float $float = 0.02): array
    {
        return [
            'id' => $id,
            'price' => 150000,
            'sold_at' => '2026-10-01T00:00:00Z',
            'item' => [
                'market_hash_name' => '★ Karambit | Doppler (Factory New)',
                'float_value' => $float,
                'paint_seed' => 100,
                'phase' => $phase,
            ],
        ];
    }
}
