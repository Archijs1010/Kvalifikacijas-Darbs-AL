<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackedSkinSalesTest extends TestCase
{
    private function skin(array $overrides = []): TrackedSkin
    {
        return TrackedSkin::create(array_merge([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'enabled' => true,
        ], $overrides));
    }

    private function sale(TrackedSkin $skin, array $overrides = []): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create(array_merge([
            'sale_id' => 'k'.$i,
            'market_hash_name' => $skin->market_hash_name,
            'price' => 33.61,
            'sold_at' => '2026-10-06 14:30:00',
            'raw_json' => [],
        ], $overrides));
    }

    private const STAT = '<p class="mt-1 text-lg font-semibold">';

    #[Test]
    public function it_shows_the_skin_name_and_its_summary_statistics(): void
    {
        $skin = $this->skin();

        foreach ([100, 200, 300, 900] as $price) {
            $this->sale($skin, ['price' => $price]);
        }

        $response = $this->get(route('skins.sales', $skin))->assertOk();

        $response->assertSee('AK-47 | Redline (Field-Tested)');

        $response->assertSee(self::STAT.'4</p>', false);
        $response->assertSee(self::STAT.'$100.00</p>', false);
        $response->assertSee(self::STAT.'$900.00</p>', false);
        $response->assertSee(self::STAT.'$375.00</p>', false);
        $response->assertSee(self::STAT.'$250.00</p>', false);
    }

    #[Test]
    public function it_shows_an_empty_state_when_the_skin_has_no_sales(): void
    {
        $skin = $this->skin();

        $response = $this->get(route('skins.sales', $skin))->assertOk();

        $response->assertSee(self::STAT.'0</p>', false);
        $response->assertSee(self::STAT.'—</p>', false);
        $response->assertSee('No sales stored for this skin yet.');
    }

    #[Test]
    public function it_shows_the_float_range_when_both_bounds_are_set(): void
    {
        $skin = $this->skin(['min_float' => '0.0000', 'max_float' => '0.0700']);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee(self::STAT.'0.0000 – 0.0700</p>', false);
    }

    #[Test]
    public function it_shows_an_open_float_range_when_only_one_bound_is_set(): void
    {
        $minOnly = $this->skin(['market_hash_name' => 'Min Only (Field-Tested)', 'min_float' => '0.0000']);
        $maxOnly = $this->skin(['market_hash_name' => 'Max Only (Field-Tested)', 'max_float' => '0.0700']);

        $this->get(route('skins.sales', $minOnly))
            ->assertOk()
            ->assertSee(self::STAT.'≥ 0.0000</p>', false);

        $this->get(route('skins.sales', $maxOnly))
            ->assertOk()
            ->assertSee(self::STAT.'≤ 0.0700</p>', false);
    }

    #[Test]
    public function it_shows_any_when_the_skin_has_no_float_bounds(): void
    {
        $skin = $this->skin();

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee(self::STAT.'Any</p>', false);
    }

    #[Test]
    public function it_paginates_the_sales_for_the_skin(): void
    {
        $skin = $this->skin();
        $base = new \DateTimeImmutable('2026-10-01 00:00:00');

        foreach (range(1, 30) as $i) {
            $this->sale($skin, [
                'price' => $i,
                'sold_at' => $base->modify("+{$i} minutes")->format('Y-m-d H:i:s'),
            ]);
        }

        $row = '<tr class="border-b border-zinc-800/60 last:border-0">';

        $first = $this->get(route('skins.sales', $skin))->assertOk();
        $this->assertSame(25, substr_count($first->getContent(), $row));
        $first->assertSee('page=2');

        $second = $this->get(route('skins.sales', ['skin' => $skin, 'page' => 2]))->assertOk();
        $this->assertSame(5, substr_count($second->getContent(), $row));
    }

    #[Test]
    public function it_only_lists_sales_belonging_to_this_skin(): void
    {
        $redline = $this->skin();
        $dragonLore = $this->skin(['market_hash_name' => 'AWP | Dragon Lore (Factory New)']);

        $this->sale($redline, ['price' => 111.11]);
        $this->sale($dragonLore, ['price' => 222.22]);

        $this->get(route('skins.sales', $redline))
            ->assertOk()
            ->assertSee('$111.11')
            ->assertDontSee('$222.22');

        $this->get(route('skins.sales', $dragonLore))
            ->assertOk()
            ->assertSee('$222.22')
            ->assertDontSee('$111.11');
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_skin(): void
    {
        $this->get('/skins/9999/sales')->assertNotFound();
    }

    #[Test]
    public function it_is_reachable_from_the_tracked_skins_table(): void
    {
        $skin = $this->skin();

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('/skins/'.$skin->id.'/sales', false);
    }

    private function chartPoints(string $html): array
    {
        preg_match('/const points = (\[.*?\]);/s', $html, $matches);

        return json_decode($matches[1] ?? '[]', true) ?? [];
    }

    #[Test]
    public function it_embeds_every_stored_sale_for_the_chart_in_date_order(): void
    {
        $skin = $this->skin();

        $this->sale($skin, ['price' => 222.22, 'sold_at' => '2026-10-02 10:00:00', 'float_value' => '0.12345678']);
        $this->sale($skin, ['price' => 111.11, 'sold_at' => '2026-10-01 10:00:00', 'float_value' => '0.20000000']);

        $response = $this->get(route('skins.sales', $skin))->assertOk();

        $this->assertSame([
            ['date' => '2026-10-01 10:00:00', 'price' => 111.11, 'float' => 0.2],
            ['date' => '2026-10-02 10:00:00', 'price' => 222.22, 'float' => 0.12345678],
        ], $this->chartPoints($response->getContent()));
    }

    #[Test]
    public function it_charts_only_the_stored_sales_of_this_skin(): void
    {
        $redline = $this->skin();
        $dragonLore = $this->skin(['market_hash_name' => 'AWP | Dragon Lore (Factory New)']);

        $this->sale($redline, ['price' => 111.11]);
        $this->sale($dragonLore, ['price' => 222.22]);

        $points = $this->chartPoints($this->get(route('skins.sales', $redline))->assertOk()->getContent());

        $this->assertSame([111.11], array_column($points, 'price'));
    }

    #[Test]
    public function it_offers_the_seven_thirty_ninety_day_and_all_ranges(): void
    {
        $skin = $this->skin();
        $this->sale($skin);

        $response = $this->get(route('skins.sales', $skin))->assertOk();

        $response->assertSee('id="price-chart"', false);
        $response->assertSee('data-range="7"', false);
        $response->assertSee('data-range="30"', false);
        $response->assertSee('data-range="90"', false);
        $response->assertSee('data-range="all"', false);
        $response->assertSee('7 days');
        $response->assertSee('30 days');
        $response->assertSee('90 days');
    }

    #[Test]
    public function it_omits_the_chart_when_the_skin_has_no_stored_sales(): void
    {
        $skin = $this->skin();

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertDontSee('id="price-chart"', false)
            ->assertDontSee('data-range="7"', false);
    }

    #[Test]
    public function it_makes_no_csfloat_request_when_rendering_the_page(): void
    {
        Http::fake();

        $skin = $this->skin();
        $this->sale($skin);

        $this->get(route('skins.sales', $skin))->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_uses_relative_days_ago_axis_labels_and_keeps_the_date_in_the_tooltip(): void
    {
        $skin = $this->skin();
        $this->sale($skin);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee("'d ago'", false)
            ->assertSee('tooltip:', false)
            ->assertSee('formatDate', false);
    }

    #[Test]
    public function it_renders_a_float_versus_price_scatter_chart(): void
    {
        $skin = $this->skin();
        $this->sale($skin, ['float_value' => '0.15000000']);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('id="scatter-chart"', false)
            ->assertSee("type: 'scatter'", false)
            ->assertSee('Float vs price')
            ->assertSee("text: 'Float'", false)
            ->assertSee("text: 'Price ($)'", false);
    }

    #[Test]
    public function it_plots_a_scatter_point_per_sale_using_float_and_price(): void
    {
        $skin = $this->skin();
        $this->sale($skin, ['float_value' => '0.10000000']);
        $this->sale($skin, ['float_value' => '0.40000000']);

        $response = $this->get(route('skins.sales', $skin))->assertOk();

        $points = $this->chartPoints($response->getContent());
        $this->assertSame([0.1, 0.4], array_column($points, 'float'));
    }

    #[Test]
    public function it_filters_the_sales_and_both_charts_by_float_range(): void
    {
        $skin = $this->skin();

        $this->sale($skin, ['price' => 100, 'float_value' => '0.10000000']);
        $this->sale($skin, ['price' => 200, 'float_value' => '0.50000000']);
        $this->sale($skin, ['price' => 300, 'float_value' => '0.90000000']);

        $response = $this->get(route('skins.sales', [
            'skin' => $skin,
            'min_float' => '0.2',
            'max_float' => '0.6',
        ]))->assertOk();

        $response->assertSee('$200.00');
        $response->assertDontSee('$100.00');
        $response->assertDontSee('$300.00');

        $points = $this->chartPoints($response->getContent());
        $this->assertEquals([200.0], array_column($points, 'price'));
        $this->assertSame([0.5], array_column($points, 'float'));
    }

    #[Test]
    public function it_shows_the_float_filter_form(): void
    {
        $skin = $this->skin();
        $this->sale($skin);

        $this->get(route('skins.sales', ['skin' => $skin, 'min_float' => '0.05']))
            ->assertOk()
            ->assertSee('name="min_float"', false)
            ->assertSee('name="max_float"', false)
            ->assertSee('value="0.05"', false);
    }

    #[Test]
    public function it_keeps_the_float_filter_on_pagination_links(): void
    {
        $skin = $this->skin();
        $base = new \DateTimeImmutable('2026-10-01 00:00:00');

        foreach (range(1, 30) as $i) {
            $this->sale($skin, [
                'price' => $i,
                'float_value' => '0.10000000',
                'sold_at' => $base->modify("+{$i} minutes")->format('Y-m-d H:i:s'),
            ]);
        }

        $this->get(route('skins.sales', ['skin' => $skin, 'min_float' => '0.05']))
            ->assertOk()
            ->assertSee('min_float=0.05', false);
    }

    #[Test]
    public function it_makes_no_csfloat_request_even_with_a_float_filter(): void
    {
        Http::fake();

        $skin = $this->skin();
        $this->sale($skin, ['float_value' => '0.15000000']);

        $this->get(route('skins.sales', ['skin' => $skin, 'min_float' => '0.1', 'max_float' => '0.2']))
            ->assertOk();

        Http::assertNothingSent();
    }
}
