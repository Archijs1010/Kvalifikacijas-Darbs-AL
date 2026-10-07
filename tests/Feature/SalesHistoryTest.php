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
}
