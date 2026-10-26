<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsChartTest extends TestCase
{
    private function skin(string $name = 'AK-47 | Redline (Field-Tested)'): TrackedSkin
    {
        return TrackedSkin::create([
            'market_hash_name' => $name,
            'enabled' => true,
        ]);
    }

    private function sale(string $name, float $price, string $soldAt, ?float $float = 0.1): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create([
            'sale_id' => 'analytics'.$i,
            'market_hash_name' => $name,
            'price' => $price,
            'float_value' => $float,
            'sold_at' => $soldAt,
            'raw_json' => [],
        ]);
    }

    private function chartPoints(string $html): array
    {
        preg_match('/const points = (\[.*?\]);/s', $html, $matches);

        return json_decode($matches[1] ?? '[]', true) ?? [];
    }

    #[Test]
    public function it_offers_all_time_periods(): void
    {
        $this->skin();
        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-10-06 12:00:00');

        $response = $this->get(route('analytics.index'))->assertOk();

        foreach (['7', '30', '90', '365', 'all'] as $range) {
            $response->assertSee('data-range="'.$range.'"', false);
        }

        $response->assertSee('7 days')->assertSee('30 days')->assertSee('90 days')
            ->assertSee('1 year')->assertSee('All');
    }

    #[Test]
    public function it_offers_the_price_float_and_volume_dataset_toggles(): void
    {
        $this->skin();
        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-01-01 12:00:00');

        $response = $this->get(route('analytics.index'))->assertOk();

        $response->assertSee('value="price"', false)
            ->assertSee('value="float"', false)
            ->assertSee('value="volume"', false)
            ->assertSee('Price')
            ->assertSee('Float')
            ->assertSee('Sales volume');
    }

    #[Test]
    public function it_embeds_price_and_float_from_local_sales(): void
    {
        $this->skin();

        $this->sale('AK-47 | Redline (Field-Tested)', 123.45, '2026-10-06 14:30:00', 0.123456);

        $html = $this->get(route('analytics.index'))->assertOk()->getContent();
        $points = $this->chartPoints($html);

        $this->assertCount(1, $points);
        $this->assertSame('2026-10-06 14:30:00', $points[0]['date']);
        $this->assertSame(123.45, $points[0]['price']);
        $this->assertSame(0.123456, $points[0]['float']);
    }

    #[Test]
    public function it_embeds_every_stored_sale_in_date_order_for_the_selected_skin(): void
    {
        $this->skin();
        $this->skin('AWP | Asiimov (Field-Tested)');

        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-10-06 12:00:00');
        $this->sale('AK-47 | Redline (Field-Tested)', 150, '2026-10-07 12:00:00');
        $this->sale('AWP | Asiimov (Field-Tested)', 999, '2026-10-07 13:00:00');

        $html = $this->get(route('analytics.index', ['skin' => 'AK-47 | Redline (Field-Tested)']))
            ->assertOk()
            ->getContent();

        $points = $this->chartPoints($html);

        $this->assertCount(2, $points);
        $this->assertSame([100.0, 150.0], array_map('floatval', array_column($points, 'price')));
    }

    #[Test]
    public function it_preserves_a_null_float_value(): void
    {
        $this->skin();
        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-10-06 12:00:00', null);

        $points = $this->chartPoints($this->get(route('analytics.index'))->assertOk()->getContent());

        $this->assertNull($points[0]['float']);
    }

    #[Test]
    public function it_keeps_the_existing_stats_working(): void
    {
        $this->skin();

        foreach ([100, 200, 300, 900] as $price) {
            $this->sale('AK-47 | Redline (Field-Tested)', $price, '2026-10-06 12:00:00');
        }

        $response = $this->get(route('analytics.index'))->assertOk();

        $response->assertSee('$375.00')
            ->assertSee('$900.00')
            ->assertSee('$100.00')
            ->assertSee('Total sales');
    }

    #[Test]
    public function it_shows_an_empty_state_without_sales(): void
    {
        $this->skin();

        $this->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('No sales to analyze yet.');
    }

    #[Test]
    public function it_makes_no_api_requests_when_rendering_or_changing_settings(): void
    {
        $this->skin();
        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-10-06 12:00:00');

        $this->get(route('analytics.index'))->assertOk();
        $this->get(route('analytics.index', ['skin' => 'AK-47 | Redline (Field-Tested)']))->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function the_chart_uses_theme_tokens_and_reacts_to_theme_changes(): void
    {
        $this->skin();
        $this->sale('AK-47 | Redline (Field-Tested)', 100, '2026-10-06 12:00:00');

        $html = $this->get(route('analytics.index'))->assertOk()->getContent();

        $this->assertStringContainsString('CSFloatTheme.color', $html);
        $this->assertStringContainsString("addEventListener('themechange'", $html);
        $this->assertStringNotContainsString("'#38bdf8'", $html);
    }
}
