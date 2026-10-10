<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaleDetailTest extends TestCase
{
    private function sale(array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'sale_id' => 'sale-1',
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => 33.61,
            'float_value' => '0.12345678',
            'sold_at' => '2026-10-06 14:30:00',
            'paint_seed' => 123,
            'raw_json' => [
                'id' => 'sale-1',
                'price' => 3361,
                'item' => ['float_value' => 0.12345678, 'paint_seed' => 123],
            ],
        ], $overrides));
    }

    #[Test]
    public function it_shows_the_sale_details(): void
    {
        $sale = $this->sale();

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('AK-47 | Redline (Field-Tested)')
            ->assertSee('$33.61')
            ->assertSee('0.123456')
            ->assertSee('123')
            ->assertSee('sale-1');
    }

    #[Test]
    public function it_renders_a_collapsible_raw_api_data_section(): void
    {
        $sale = $this->sale();

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertSee('Raw API Data');
    }

    #[Test]
    public function it_formats_the_raw_json_readably(): void
    {
        $sale = $this->sale();

        $html = $this->get(route('sales.show', $sale))->assertOk()->getContent();

        preg_match('#<pre[^>]*>(.*?)</pre>#s', $html, $matches);
        $pre = $matches[1] ?? '';

        $this->assertStringContainsString("\n", $pre);
        $this->assertSame($sale->raw_json, json_decode(html_entity_decode($pre), true));
    }

    #[Test]
    public function it_renders_an_empty_payload(): void
    {
        $sale = $this->sale(['raw_json' => []]);

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('[]');
    }

    #[Test]
    public function it_escapes_the_raw_json_so_it_cannot_inject_markup(): void
    {
        $sale = $this->sale(['raw_json' => ['note' => '<script>alert(1)</script>']]);

        $response = $this->get(route('sales.show', $sale))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    #[Test]
    public function it_does_not_expose_keys_or_environment_secrets(): void
    {
        config(['services.csfloat.key' => 'SECRET-CSFLOAT-KEY']);
        $appKey = (string) config('app.key');

        $sale = $this->sale();

        $response = $this->get(route('sales.show', $sale))->assertOk();

        $response->assertDontSee('SECRET-CSFLOAT-KEY');
        $response->assertDontSee($appKey);
        $response->assertDontSee('APP_KEY');
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_sale(): void
    {
        $this->get('/sales/9999')->assertNotFound();
    }

    #[Test]
    public function it_makes_no_csfloat_request(): void
    {
        Http::fake();

        $sale = $this->sale();

        $this->get(route('sales.show', $sale))->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function the_sales_history_and_skin_pages_link_to_the_sale_details(): void
    {
        $sale = $this->sale();

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('/sales/'.$sale->id, false);

        $skin = TrackedSkin::create([
            'market_hash_name' => $sale->market_hash_name,
            'enabled' => true,
        ]);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee('/sales/'.$sale->id, false);
    }
}
