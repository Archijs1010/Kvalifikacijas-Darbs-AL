<?php

namespace Tests\Unit;

use App\Services\CSFloatSaleMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\LoadsCSFloatFixtures;
use Tests\TestCase;

class CSFloatSaleMapperTest extends TestCase
{
    use LoadsCSFloatFixtures;

    private CSFloatSaleMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new CSFloatSaleMapper;
    }

    #[Test]
    public function it_maps_a_real_captured_sale_onto_the_sales_table_shape(): void
    {
        $entry = $this->csfloatSaleEntry(0);

        $mapped = $this->mapper->map($entry);

        $this->assertSame((string) $entry['id'], $mapped['sale_id']);
        $this->assertSame($entry['item']['market_hash_name'], $mapped['market_hash_name']);
        $this->assertSame(33.61, $mapped['price']);
        $this->assertSame(0.218822181224823, $mapped['float_value']);
        $this->assertSame(282, $mapped['paint_index']);
        $this->assertSame('2026-09-30T10:25:23.886716Z', $mapped['sold_at']->format('Y-m-d\TH:i:s.u\Z'));
    }

    #[Test]
    public function it_reads_float_value_from_the_nested_item_object(): void
    {
        $entry = $this->csfloatSaleEntry(0);

        $this->assertArrayNotHasKey('float_value', $entry, 'Fixture sanity: float_value is not top level.');

        $mapped = $this->mapper->map($entry);

        $this->assertSame($entry['item']['float_value'], $mapped['float_value']);
    }

    #[Test]
    public function it_reads_paint_index_from_the_nested_item_object(): void
    {
        $entry = $this->csfloatSaleEntry(0);

        $this->assertArrayNotHasKey('paint_index', $entry, 'Fixture sanity: paint_index is not top level.');

        $this->assertSame($entry['item']['paint_index'], $this->mapper->map($entry)['paint_index']);
    }

    #[Test]
    public function it_reads_market_hash_name_from_the_nested_item_object(): void
    {
        $entry = $this->csfloatSaleEntry(2);

        $this->assertSame('AWP | Dragon Lore (Factory New)', $entry['item']['market_hash_name']);
        $this->assertArrayNotHasKey('market_hash_name', $entry);

        $this->assertSame('AWP | Dragon Lore (Factory New)', $this->mapper->map($entry)['market_hash_name']);
    }

    #[Test]
    public function it_converts_price_from_integer_cents_to_decimal_units(): void
    {
        $this->assertSame(10941.36, $this->mapper->map($this->csfloatSaleEntry(2))['price']);
        $this->assertSame(25.48, $this->mapper->map($this->csfloatSaleEntry(1))['price']);
    }

    #[Test]
    public function it_preserves_full_float_precision(): void
    {
        $this->assertSame(0.031230954453349113, $this->mapper->map($this->csfloatSaleEntry(2))['float_value']);
    }

    #[Test]
    public function it_stores_the_complete_raw_entry(): void
    {
        $entry = $this->csfloatSaleEntry(0);

        $this->assertSame($entry, $this->mapper->map($entry)['raw_json']);
    }

    #[Test]
    public function it_leaves_float_value_null_when_csfloat_omits_it(): void
    {
        $entry = ['id' => '1', 'price' => 100, 'sold_at' => '2026-01-01T00:00:00Z', 'item' => ['def_index' => 7]];

        $this->assertNull($this->mapper->map($entry)['float_value']);
    }

    #[Test]
    public function it_leaves_paint_index_null_for_unpainted_items(): void
    {
        $entry = [
            'id' => '1',
            'price' => 100,
            'sold_at' => '2026-01-01T00:00:00Z',
            'item' => ['float_value' => 0.05, 'market_hash_name' => 'Gloves | Specialist (Factory New)'],
        ];

        $this->assertNull($this->mapper->map($entry)['paint_index']);
    }

    #[Test]
    public function it_leaves_sold_at_null_when_absent_or_unparseable(): void
    {
        $this->assertNull($this->mapper->map(['id' => '1', 'price' => 100, 'item' => []])['sold_at']);
        $this->assertNull($this->mapper->map([
            'id' => '1',
            'price' => 100,
            'sold_at' => 'not-a-date',
            'item' => [],
        ])['sold_at']);
    }

    #[Test]
    public function it_leaves_price_null_instead_of_inventing_zero(): void
    {
        $mapped = $this->mapper->map(['id' => '1', 'item' => ['market_hash_name' => 'AWP | Dragon Lore (Factory New)']]);

        $this->assertNull($mapped['price']);
    }

    #[Test]
    public function it_keeps_a_genuine_zero_price(): void
    {
        $mapped = $this->mapper->map([
            'id' => '1',
            'price' => 0,
            'sold_at' => '2026-01-01T00:00:00Z',
            'item' => ['market_hash_name' => 'AWP | Dragon Lore (Factory New)'],
        ]);

        $this->assertSame(0.0, $mapped['price']);
    }

    #[Test]
    public function it_returns_null_for_an_entry_without_a_sale_id(): void
    {
        $this->assertNull($this->mapper->map(['price' => 100, 'item' => []]));
        $this->assertNull($this->mapper->map(['id' => '   ', 'price' => 100, 'item' => []]));
    }

    #[Test]
    public function it_accepts_an_integer_sale_id(): void
    {
        $this->assertSame('42', $this->mapper->map(['id' => 42, 'price' => 100, 'item' => []])['sale_id']);
    }

    #[Test]
    public function it_falls_back_to_the_tracked_skin_name_when_the_item_has_none(): void
    {
        $mapped = $this->mapper->map(['id' => '1', 'price' => 100, 'item' => []], 'AK-47 | Redline (Field-Tested)');

        $this->assertSame('AK-47 | Redline (Field-Tested)', $mapped['market_hash_name']);
    }

    #[Test]
    public function it_prefers_the_market_hash_name_from_the_response(): void
    {
        $mapped = $this->mapper->map($this->csfloatSaleEntry(0), 'Some Other Skin (Field-Tested)');

        $this->assertSame('AK-47 | Redline (Field-Tested)', $mapped['market_hash_name']);
    }

    #[Test]
    public function it_reads_a_bare_array_response(): void
    {
        $sales = $this->csfloatSalesFixture();

        $this->assertCount(count($sales), $this->mapper->salesList($sales));
    }

    #[Test]
    public function it_reads_a_data_wrapped_response(): void
    {
        $sales = $this->csfloatSalesFixture();

        $this->assertCount(count($sales), $this->mapper->salesList(['data' => $sales]));
    }

    #[Test]
    public function it_drops_non_array_entries_from_the_sales_list(): void
    {
        $this->assertSame(
            [['id' => '1'], ['id' => '2']],
            $this->mapper->salesList([['id' => '1'], null, 'nope', ['id' => '2']])
        );
    }
}
