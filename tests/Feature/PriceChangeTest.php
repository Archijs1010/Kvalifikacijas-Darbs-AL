<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceChangeTest extends TestCase
{
    private function skin(array $overrides = []): TrackedSkin
    {
        return TrackedSkin::create(array_merge([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'enabled' => true,
        ], $overrides));
    }

    private function sale(TrackedSkin $skin, string $soldAt, float $price): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create([
            'sale_id' => 'p'.$i,
            'market_hash_name' => $skin->market_hash_name,
            'price' => $price,
            'float_value' => 0.1,
            'sold_at' => $soldAt,
            'raw_json' => [],
        ]);
    }

    #[Test]
    public function it_shows_not_enough_data_when_there_is_no_history(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $skin = $this->skin();
        $this->sale($skin, '2026-10-13 12:00:00', 120);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('Not enough data');

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('Not enough data');
    }

    #[Test]
    public function it_shows_the_percentage_with_reference_and_current_averages(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $skin = $this->skin();
        $this->sale($skin, '2026-10-05 12:00:00', 100);
        $this->sale($skin, '2026-10-13 12:00:00', 120);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('20.00%')
            ->assertSee('$100.00')
            ->assertSee('$120.00');
    }

    #[Test]
    public function it_shows_the_price_change_panel_on_the_skin_page(): void
    {
        $skin = $this->skin();

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('Price change')
            ->assertSee('7-day')
            ->assertSee('30-day')
            ->assertSee('All-time');
    }

    #[Test]
    public function it_shows_the_all_time_change_once_there_are_enough_sales(): void
    {
        $skin = $this->skin();
        $this->sale($skin, '2026-01-01 12:00:00', 100);
        $this->sale($skin, '2026-01-02 12:00:00', 100);
        $this->sale($skin, '2026-06-01 12:00:00', 150);
        $this->sale($skin, '2026-06-02 12:00:00', 150);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('All-time')
            ->assertSee('50.00%');
    }

    #[Test]
    public function it_makes_no_api_requests_when_showing_price_changes(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $skin = $this->skin();
        $this->sale($skin, '2026-10-05 12:00:00', 100);
        $this->sale($skin, '2026-10-13 12:00:00', 120);

        $this->get(route('skins.index'))->assertOk();
        $this->get(route('skins.sales', $skin))->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_ignores_sales_belonging_to_other_skins(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $skin = $this->skin();
        $other = $this->skin(['market_hash_name' => 'AWP | Asiimov (Field-Tested)']);

        $this->sale($other, '2026-10-05 12:00:00', 100);
        $this->sale($other, '2026-10-13 12:00:00', 120);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('Not enough data');
    }

    #[Test]
    public function it_uses_all_stored_sales_regardless_of_the_float_filter(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $skin = $this->skin();
        $this->sale($skin, '2026-10-05 12:00:00', 100);
        $this->sale($skin, '2026-10-13 12:00:00', 120);

        $this->get(route('skins.sales', $skin, ['min_float' => 0.5]))
            ->assertOk()
            ->assertSee('20.00%');
    }

    #[Test]
    public function it_shows_the_change_for_every_skin_on_the_list(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $first = $this->skin(['market_hash_name' => 'AK-47 | Redline (Field-Tested)']);
        $second = $this->skin(['market_hash_name' => 'AWP | Asiimov (Field-Tested)']);

        $this->sale($first, '2026-10-05 12:00:00', 100);
        $this->sale($first, '2026-10-13 12:00:00', 120);

        $this->sale($second, '2026-10-05 12:00:00', 100);
        $this->sale($second, '2026-10-13 12:00:00', 110);

        $this->get(route('skins.index'))
            ->assertOk()
            ->assertSee('20.00%')
            ->assertSee('10.00%');
    }
}
