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

    /**
     * Route a CSFloat history request to the captured fixture for the matching skin.
     *
     * @param  array<string, mixed>  $overrides  skin name => payload
     */
    private function fakeCSFloat(array $overrides = []): void
    {
        $sales = collect($this->csfloatSalesFixture())
            ->groupBy(fn (array $sale) => $sale['item']['market_hash_name'])
            ->map(fn ($group) => $group->values()->all())
            ->all();

        Http::fake(function (Request $request) use ($sales, $overrides) {
            foreach (array_merge($sales, $overrides) as $skin => $payload) {
                if (str_contains($request->url(), rawurlencode($skin))) {
                    return Http::response($payload, 200);
                }
            }

            return Http::response([], 404);
        });
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
}
