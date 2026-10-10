<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\LoadsCSFloatFixtures;
use Tests\TestCase;

class ImportSalesTest extends TestCase
{
    use LoadsCSFloatFixtures;

    private function fakeCSFloat(array $overrides = []): void
    {
        $sales = $this->groupedFixture();

        Http::fake(function (Request $request) use ($sales, $overrides) {
            foreach (array_merge($sales, $overrides) as $skin => $payload) {
                if (str_contains($request->url(), rawurlencode($skin))) {
                    return Http::response($payload, 200);
                }
            }

            return Http::response([], 404);
        });
    }

    private function groupedFixture(): array
    {
        return collect($this->csfloatSalesFixture())
            ->groupBy(fn (array $sale) => $sale['item']['market_hash_name'])
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    #[Test]
    public function it_imports_the_captured_sales_with_values_from_the_real_response(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 2, 'skipped' => 0, 'malformed' => 0, 'failed_skins' => []]);

        $sale = Sale::where('sale_id', '1025352651652597907')->firstOrFail();

        $this->assertSame('AK-47 | Redline (Field-Tested)', $sale->market_hash_name);
        $this->assertSame('33.61', $sale->price);
        $this->assertSame('0.21882218', $sale->float_value);
        $this->assertSame(282, $sale->paint_index);
        $this->assertSame('2026-09-30 10:25:23', $sale->sold_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_stores_the_complete_raw_json(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))->assertOk();

        $entry = $this->csfloatSaleEntry(0);
        $sale = Sale::where('sale_id', $entry['id'])->firstOrFail();

        $this->assertSame($entry, $sale->raw_json);
        $this->assertSame($entry['item']['asset_id'], $sale->raw_json['item']['asset_id']);
        $this->assertSame($entry['item']['icon_url'], $sale->raw_json['item']['icon_url']);
    }

    #[Test]
    public function it_imports_every_enabled_skin_in_the_captured_payload(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'AWP | Dragon Lore (Factory New)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 4, 'skipped' => 0, 'malformed' => 0]);

        $this->assertSame(4, Sale::count());
    }

    #[Test]
    public function it_skips_skins_that_are_not_enabled(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => false]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 0, 'skipped' => 0]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_does_not_duplicate_sales_on_a_second_import(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))->assertOk()->assertJson(['imported' => 2]);
        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 0, 'skipped' => 2]);

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_persists_a_null_float_instead_of_failing_the_import(): void
    {
        $this->fakeCSFloat([
            'AK-47 | Redline (Field-Tested)' => [
                [
                    'id' => '999',
                    'price' => 1500,
                    'sold_at' => '2026-01-01T00:00:00Z',
                    'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'def_index' => 7],
                ],
            ],
        ]);
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 1, 'malformed' => 0]);

        $this->assertNull(Sale::where('sale_id', '999')->firstOrFail()->float_value);
    }

    #[Test]
    public function it_persists_a_null_paint_index_for_unpainted_items(): void
    {
        $this->fakeCSFloat([
            'AK-47 | Redline (Field-Tested)' => [
                [
                    'id' => '1000',
                    'price' => 1500,
                    'sold_at' => '2026-01-01T00:00:00Z',
                    'item' => [
                        'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
                        'float_value' => 0.11,
                    ],
                ],
            ],
        ]);
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))->assertOk();

        $this->assertNull(Sale::where('sale_id', '1000')->firstOrFail()->paint_index);
    }

    #[Test]
    public function it_counts_entries_without_a_sale_id_as_malformed(): void
    {
        $this->fakeCSFloat([
            'AK-47 | Redline (Field-Tested)' => [
                ['price' => 1500, 'item' => []],
                ['id' => '1001', 'price' => 1500, 'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)']],
            ],
        ]);
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 1, 'malformed' => 1]);

        $this->assertSame(1, Sale::count());
    }

    #[Test]
    public function it_counts_entries_without_a_price_as_malformed_rather_than_storing_zero(): void
    {
        $this->fakeCSFloat([
            'AK-47 | Redline (Field-Tested)' => [
                ['id' => '1002', 'item' => ['market_hash_name' => 'AK-47 | Redline (Field-Tested)']],
            ],
        ]);
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 0, 'malformed' => 1]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_reports_a_skin_as_failed_when_csfloat_errors(): void
    {
        Http::fake([
            'csfloat.com/*' => Http::response(['error' => 'unauthorized'], 403),
        ]);
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'failed_skins' => ['AK-47 | Redline (Field-Tested)'],
            ]);

        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_returns_an_empty_result_when_no_skins_are_tracked(): void
    {
        $this->fakeCSFloat();

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson(['imported' => 0, 'skipped' => 0, 'malformed' => 0, 'failed_skins' => []]);
    }

    #[Test]
    public function it_reports_successful_skins_and_duplicates_for_the_summary(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'AWP | Dragon Lore (Factory New)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 4,
                'duplicates' => 0,
                'successful_skins' => [
                    'AK-47 | Redline (Field-Tested)',
                    'AWP | Dragon Lore (Factory New)',
                ],
                'failed_skins' => [],
                'rate_limited' => false,
                'not_attempted' => [],
            ]);
    }

    #[Test]
    public function it_counts_duplicates_separately_from_other_skipped_sales(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))->assertOk()->assertJson([
            'imported' => 2,
            'skipped' => 0,
            'duplicates' => 0,
        ]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'skipped' => 2,
                'duplicates' => 2,
                'successful_skins' => ['AK-47 | Redline (Field-Tested)'],
            ]);
    }

    #[Test]
    public function it_keeps_importing_the_remaining_skins_after_one_fails(): void
    {
        $payloads = $this->groupedFixture();

        Http::fake(function (Request $request) use ($payloads) {
            if (str_contains($request->url(), rawurlencode('AK-47 | Redline (Field-Tested)'))) {
                return Http::response(['error' => 'forbidden'], 403);
            }

            return Http::response($payloads['AWP | Dragon Lore (Factory New)'], 200);
        });

        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'AWP | Dragon Lore (Factory New)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 2,
                'successful_skins' => ['AWP | Dragon Lore (Factory New)'],
                'failed_skins' => ['AK-47 | Redline (Field-Tested)'],
                'rate_limited' => false,
                'not_attempted' => [],
            ]);

        $this->assertSame(2, Sale::count());
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_stops_the_run_and_sends_no_further_requests_when_rate_limited(): void
    {
        $payloads = $this->groupedFixture();

        Http::fake(function (Request $request) use ($payloads) {
            if (str_contains($request->url(), rawurlencode('AK-47 | Redline (Field-Tested)'))) {
                return Http::response(['error' => 'rate limited'], 429);
            }

            return Http::response($payloads['AWP | Dragon Lore (Factory New)'], 200);
        });

        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'AWP | Dragon Lore (Factory New)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'M4A4 | Howl (Factory New)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'rate_limited' => true,
                'failed_skins' => ['AK-47 | Redline (Field-Tested)'],
                'successful_skins' => [],
                'not_attempted' => [
                    'AWP | Dragon Lore (Factory New)',
                    'M4A4 | Howl (Factory New)',
                ],
            ]);

        $this->assertSame(0, Sale::count());
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_makes_exactly_one_request_per_enabled_skin(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'AWP | Dragon Lore (Factory New)', 'enabled' => true]);
        TrackedSkin::create(['market_hash_name' => 'Disabled Skin (Field-Tested)', 'enabled' => false]);

        $this->postJson(route('import-sales'))->assertOk();

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_reports_a_skin_with_an_empty_response_as_successful(): void
    {
        Http::fake(fn () => Http::response([], 200));
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => true]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'successful_skins' => ['AK-47 | Redline (Field-Tested)'],
                'failed_skins' => [],
                'no_sales_skins' => ['AK-47 | Redline (Field-Tested)'],
            ]);
    }

    #[Test]
    public function it_makes_no_requests_at_all_when_no_skin_is_enabled(): void
    {
        $this->fakeCSFloat();
        TrackedSkin::create(['market_hash_name' => 'AK-47 | Redline (Field-Tested)', 'enabled' => false]);

        $this->postJson(route('import-sales'))
            ->assertOk()
            ->assertJson([
                'imported' => 0,
                'successful_skins' => [],
                'failed_skins' => [],
            ]);

        Http::assertNothingSent();
    }

    #[Test]
    public function the_dashboard_offers_the_import_all_sales_button_and_its_summary(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Import All Sales', false)
            ->assertSee('id="import-form"', false)
            ->assertSee('id="import-summary"', false)
            ->assertSee('Successful skins', false)
            ->assertSee('Failed skins', false)
            ->assertSee('New sales imported', false)
            ->assertSee('Duplicate sales skipped', false)
            ->assertSee('id="rate-limit-note"', false);
    }
}
