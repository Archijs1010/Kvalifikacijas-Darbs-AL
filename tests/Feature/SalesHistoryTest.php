<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Services\CSFloatSaleMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\LoadsCSFloatFixtures;
use Tests\TestCase;

class SalesHistoryTest extends TestCase
{
    use LoadsCSFloatFixtures;

    #[Test]
    public function it_shows_the_paint_seed_for_every_sale(): void
    {
        Sale::create([
            'sale_id' => '1',
            'market_hash_name' => 'AK-47 | Case Hardened (Field-Tested)',
            'price' => 150.00,
            'float_value' => 0.0071,
            'sold_at' => '2026-10-06 14:30:00',
            'paint_index' => 44,
            'paint_seed' => 661,
            'raw_json' => [],
        ]);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Seed')
            ->assertSee('>661<', false);
    }

    #[Test]
    public function it_shows_a_dash_when_a_sale_has_no_paint_seed(): void
    {
        Sale::create([
            'sale_id' => '1',
            'market_hash_name' => 'AWP | Dragon Lore (Factory New)',
            'price' => 10941.36,
            'sold_at' => '2026-10-06 14:30:00',
            'raw_json' => [],
        ]);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Seed')
            ->assertSee('—');
    }

    #[Test]
    public function it_shows_when_the_sale_happened(): void
    {
        Sale::create([
            'sale_id' => '1',
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => 33.61,
            'sold_at' => '2026-10-06 14:30:00',
            'raw_json' => [],
        ]);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Date')
            ->assertSee('Oct 6, 2026 14:30');
    }

    #[Test]
    public function it_reads_a_real_fixture_sale_end_to_end(): void
    {
        $entry = $this->csfloatSaleEntry(0);
        $mapped = app(CSFloatSaleMapper::class)->map($entry);
        Sale::create($mapped);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee($entry['item']['market_hash_name'])
            ->assertSee('>125<', false)
            ->assertSee('Sep 30, 2026 10:25');
    }

    #[Test]
    public function it_persists_the_seed_as_an_integer(): void
    {
        Sale::create([
            'sale_id' => '1',
            'market_hash_name' => 'AK-47 | Case Hardened (Field-Tested)',
            'price' => 150.00,
            'sold_at' => '2026-10-06 14:30:00',
            'paint_seed' => 661,
            'raw_json' => [],
        ]);

        $this->assertSame(661, Sale::sole()->paint_seed);
        $this->assertIsInt(Sale::sole()->paint_seed);
    }

    #[Test]
    public function it_shows_the_phase_for_each_sale(): void
    {
        $this->doppler('Phase 4');
        $this->doppler('Ruby');

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Phase 4</td>', false)
            ->assertSee('Ruby</td>', false);
    }

    #[Test]
    public function it_shows_a_dash_for_phaseless_sales(): void
    {
        Sale::create([
            'sale_id' => '1',
            'market_hash_name' => '★ Sport Gloves | Vice (Field-Tested)',
            'price' => 500.00,
            'sold_at' => '2026-10-06 14:30:00',
            'raw_json' => [],
        ]);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('—</td>', false);
    }

    #[Test]
    public function it_lists_only_the_phases_actually_in_the_database(): void
    {
        $this->doppler('Phase 4');
        $this->doppler('Sapphire');

        $html = $this->get(route('sales.index'))->assertOk()->getContent();

        $this->assertStringContainsString('value="Phase 4"', $html);
        $this->assertStringContainsString('value="Sapphire"', $html);
        $this->assertStringNotContainsString('value="Ruby"', $html);
    }

    #[Test]
    public function it_filters_sales_down_to_a_single_phase(): void
    {
        $this->doppler('Phase 1', 101.11);
        $this->doppler('Phase 4', 202.22);

        $this->get(route('sales.index', ['phase' => 'Phase 4']))
            ->assertOk()
            ->assertSee('$202.22')
            ->assertDontSee('$101.11');
    }

    #[Test]
    public function it_filters_the_phase_regardless_of_casing(): void
    {
        $this->doppler('Phase 4', 303.33);
        $this->doppler('Ruby', 404.44);

        $this->get(route('sales.index', ['phase' => 'pHaSe 4']))
            ->assertOk()
            ->assertSee('$303.33')
            ->assertDontSee('$404.44');
    }

    #[Test]
    public function it_keeps_the_phase_when_other_filters_are_applied(): void
    {
        $this->doppler('Phase 4', 505.55);
        $this->doppler('Ruby', 606.66);

        $this->get(route('sales.index', [
            'skin' => '★ Karambit | Doppler (Factory New)',
            'phase' => 'Ruby',
        ]))
            ->assertOk()
            ->assertSee('$606.66')
            ->assertDontSee('$505.55');
    }

    private function doppler(string $phase, float $price = 1457.69): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create([
            'sale_id' => 'p'.$i,
            'market_hash_name' => '★ Karambit | Doppler (Factory New)',
            'price' => $price,
            'sold_at' => '2026-10-06 14:30:00',
            'phase' => $phase,
            'raw_json' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sale(array $overrides = []): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create(array_merge([
            'sale_id' => 's'.$i,
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => 33.61,
            'sold_at' => '2026-10-06 14:30:00',
            'raw_json' => [],
        ], $overrides));
    }

    #[Test]
    public function it_shows_the_skin_price_float_and_sale_date_columns(): void
    {
        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('>Skin<', false)
            ->assertSee('>Price<', false)
            ->assertSee('>Float<', false)
            ->assertSee('>Date<', false);
    }

    #[Test]
    public function it_offers_every_filter_on_the_sales_history_form(): void
    {
        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('name="skin"', false)
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSee('name="min_float"', false)
            ->assertSee('name="max_float"', false)
            ->assertSee('name="min_price"', false)
            ->assertSee('name="max_price"', false)
            ->assertSee('name="phase"', false);
    }

    #[Test]
    public function it_filters_sales_on_or_after_the_from_date(): void
    {
        $this->sale(['price' => 11.11, 'sold_at' => '2026-10-01 12:00:00']);
        $this->sale(['price' => 22.22, 'sold_at' => '2026-10-05 12:00:00']);

        $this->get(route('sales.index', ['from' => '2026-10-05']))
            ->assertOk()
            ->assertSee('$22.22')
            ->assertDontSee('$11.11');
    }

    #[Test]
    public function it_includes_the_whole_day_in_the_to_date(): void
    {
        $this->sale(['price' => 11.11, 'sold_at' => '2026-10-01 12:00:00']);
        $this->sale(['price' => 22.22, 'sold_at' => '2026-10-05 23:59:59']);
        $this->sale(['price' => 33.33, 'sold_at' => '2026-10-06 00:00:01']);

        $this->get(route('sales.index', ['to' => '2026-10-05']))
            ->assertOk()
            ->assertSee('$11.11')
            ->assertSee('$22.22')
            ->assertDontSee('$33.33');
    }

    #[Test]
    public function it_combines_the_from_and_to_dates_into_a_window(): void
    {
        $this->sale(['price' => 11.11, 'sold_at' => '2026-10-01 12:00:00']);
        $this->sale(['price' => 22.22, 'sold_at' => '2026-10-05 12:00:00']);
        $this->sale(['price' => 33.33, 'sold_at' => '2026-10-09 12:00:00']);

        $this->get(route('sales.index', ['from' => '2026-10-04', 'to' => '2026-10-06']))
            ->assertOk()
            ->assertSee('$22.22')
            ->assertDontSee('$11.11')
            ->assertDontSee('$33.33');
    }

    #[Test]
    public function it_ignores_a_malformed_date_filter(): void
    {
        $this->sale(['price' => 11.11, 'sold_at' => '2026-10-01 12:00:00']);

        $this->get(route('sales.index', ['from' => 'not-a-date', 'to' => '']))
            ->assertOk()
            ->assertSee('$11.11');
    }

    #[Test]
    public function it_filters_sales_by_a_price_range(): void
    {
        $this->sale(['price' => 10.00]);
        $this->sale(['price' => 50.00]);
        $this->sale(['price' => 90.00]);

        $this->get(route('sales.index', ['min_price' => 20, 'max_price' => 60]))
            ->assertOk()
            ->assertSee('$50.00')
            ->assertDontSee('$10.00')
            ->assertDontSee('$90.00');
    }

    #[Test]
    public function it_accepts_an_open_ended_price_bound(): void
    {
        $this->sale(['price' => 10.00]);
        $this->sale(['price' => 50.00]);
        $this->sale(['price' => 90.00]);

        $this->get(route('sales.index', ['min_price' => 60]))
            ->assertOk()
            ->assertSee('$90.00')
            ->assertDontSee('$10.00')
            ->assertDontSee('$50.00');
    }

    #[Test]
    public function it_ignores_a_non_numeric_price_filter(): void
    {
        $this->sale(['price' => 10.00]);

        $this->get(route('sales.index', ['min_price' => 'cheap', 'max_price' => '']))
            ->assertOk()
            ->assertSee('$10.00');
    }

    #[Test]
    public function it_paginates_the_sales_history(): void
    {
        $base = new \DateTimeImmutable('2026-10-01 00:00:00');

        foreach (range(1, 30) as $i) {
            $this->sale([
                'price' => $i,
                'sold_at' => $base->modify("+{$i} minutes")->format('Y-m-d H:i:s'),
            ]);
        }

        $first = $this->get(route('sales.index'))->assertOk();
        $this->assertSame(25, substr_count($first->getContent(), 'AK-47 | Redline (Field-Tested)'));
        $first->assertSee('page=2');

        $second = $this->get(route('sales.index', ['page' => 2]))->assertOk();
        $this->assertSame(5, substr_count($second->getContent(), 'AK-47 | Redline (Field-Tested)'));
    }

    #[Test]
    public function it_carries_the_active_filters_onto_the_next_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->sale(['price' => $i]);
        }

        $first = $this->get(route('sales.index', ['min_price' => 5]))->assertOk();

        $this->assertSame(25, substr_count($first->getContent(), 'AK-47 | Redline (Field-Tested)'));
        $first->assertSee('min_price=5', false);

        $second = $this->get(route('sales.index', ['min_price' => 5, 'page' => 2]))->assertOk();
        $this->assertSame(1, substr_count($second->getContent(), 'AK-47 | Redline (Field-Tested)'));
    }
}
