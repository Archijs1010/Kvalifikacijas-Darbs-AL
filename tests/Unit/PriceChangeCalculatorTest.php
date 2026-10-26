<?php

namespace Tests\Unit;

use App\Models\Sale;
use App\Services\PriceChangeCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceChangeCalculatorTest extends TestCase
{
    private PriceChangeCalculator $calculator;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PriceChangeCalculator;
        $this->now = Carbon::parse('2026-10-15 12:00:00');
    }

    private function sale(string $soldAt, float $price): Sale
    {
        return new Sale([
            'sale_id' => 'x'.uniqid(),
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'price' => $price,
            'sold_at' => $soldAt,
            'raw_json' => [],
        ]);
    }

    private function calculate(array $sales): array
    {
        return $this->calculator->calculate(collect($sales), $this->now);
    }

    #[Test]
    public function it_reports_a_positive_seven_day_change(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-05 12:00:00', 100),
            $this->sale('2026-10-13 12:00:00', 120),
        ])['7d'];

        $this->assertTrue($result['available']);
        $this->assertSame(20.0, $result['percent']);
        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(120.0, $result['current']);
        $this->assertSame(1, $result['previous_count']);
        $this->assertSame(1, $result['current_count']);
    }

    #[Test]
    public function it_reports_a_negative_seven_day_change(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-05 12:00:00', 200),
            $this->sale('2026-10-13 12:00:00', 150),
        ])['7d'];

        $this->assertTrue($result['available']);
        $this->assertSame(-25.0, $result['percent']);
        $this->assertSame(200.0, $result['previous']);
        $this->assertSame(150.0, $result['current']);
    }

    #[Test]
    public function it_averages_multiple_sales_within_each_window(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-04 12:00:00', 100),
            $this->sale('2026-10-06 12:00:00', 200),
            $this->sale('2026-10-12 12:00:00', 150),
            $this->sale('2026-10-14 12:00:00', 250),
        ])['7d'];

        $this->assertSame(150.0, $result['previous']);
        $this->assertSame(200.0, $result['current']);
        $this->assertSame(33.33, $result['percent']);
        $this->assertSame(2, $result['previous_count']);
        $this->assertSame(2, $result['current_count']);
    }

    #[Test]
    public function it_is_not_available_without_a_previous_window(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-13 12:00:00', 120),
        ])['7d'];

        $this->assertFalse($result['available']);
        $this->assertNull($result['percent']);
        $this->assertNull($result['previous']);
        $this->assertNull($result['current']);
    }

    #[Test]
    public function it_is_not_available_without_a_current_window(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-05 12:00:00', 100),
        ])['7d'];

        $this->assertFalse($result['available']);
    }

    #[Test]
    public function it_reports_zero_change_when_averages_match(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-05 12:00:00', 100),
            $this->sale('2026-10-13 12:00:00', 100),
        ])['7d'];

        $this->assertTrue($result['available']);
        $this->assertSame(0.0, $result['percent']);
    }

    #[Test]
    public function it_includes_sales_exactly_on_the_window_boundaries(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-01 12:00:00', 100),
            $this->sale('2026-10-08 12:00:00', 200),
        ])['7d'];

        $this->assertSame(1, $result['previous_count']);
        $this->assertSame(1, $result['current_count']);
        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(200.0, $result['current']);
    }

    #[Test]
    public function it_ignores_sales_older_than_the_previous_window(): void
    {
        $result = $this->calculate([
            $this->sale('2026-09-20 12:00:00', 999),
            $this->sale('2026-10-05 12:00:00', 100),
            $this->sale('2026-10-13 12:00:00', 120),
        ])['7d'];

        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(120.0, $result['current']);
    }

    #[Test]
    public function it_uses_a_sixty_day_span_for_the_thirty_day_change(): void
    {
        $result = $this->calculate([
            $this->sale('2026-08-01 12:00:00', 500),
            $this->sale('2026-09-01 12:00:00', 100),
            $this->sale('2026-09-20 12:00:00', 110),
        ])['30d'];

        $this->assertTrue($result['available']);
        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(110.0, $result['current']);
        $this->assertSame(10.0, $result['percent']);
    }

    #[Test]
    public function it_is_not_available_when_the_reference_average_is_zero(): void
    {
        $result = $this->calculate([
            $this->sale('2026-10-05 12:00:00', 0),
            $this->sale('2026-10-13 12:00:00', 10),
        ])['7d'];

        $this->assertFalse($result['available']);
    }

    #[Test]
    public function it_reports_the_all_time_change_by_splitting_the_history_in_half(): void
    {
        $result = $this->calculate([
            $this->sale('2026-01-01 12:00:00', 120),
            $this->sale('2026-01-02 12:00:00', 80),
            $this->sale('2026-06-01 12:00:00', 130),
            $this->sale('2026-06-02 12:00:00', 170),
        ])['all'];

        $this->assertTrue($result['available']);
        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(150.0, $result['current']);
        $this->assertSame(50.0, $result['percent']);
    }

    #[Test]
    public function it_sorts_all_time_sales_by_date_regardless_of_input_order(): void
    {
        $result = $this->calculate([
            $this->sale('2026-06-01 12:00:00', 130),
            $this->sale('2026-01-01 12:00:00', 100),
            $this->sale('2026-06-02 12:00:00', 150),
            $this->sale('2026-01-02 12:00:00', 100),
        ])['all'];

        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(140.0, $result['current']);
    }

    #[Test]
    public function it_needs_enough_sales_for_an_all_time_change(): void
    {
        $result = $this->calculate([
            $this->sale('2026-01-01 12:00:00', 100),
            $this->sale('2026-06-01 12:00:00', 150),
            $this->sale('2026-06-02 12:00:00', 160),
        ])['all'];

        $this->assertFalse($result['available']);
    }

    #[Test]
    public function it_splits_an_odd_all_time_count_by_index(): void
    {
        $result = $this->calculate([
            $this->sale('2026-01-01 12:00:00', 100),
            $this->sale('2026-01-02 12:00:00', 100),
            $this->sale('2026-06-01 12:00:00', 160),
            $this->sale('2026-06-02 12:00:00', 160),
            $this->sale('2026-06-03 12:00:00', 160),
        ])['all'];

        $this->assertTrue($result['available']);
        $this->assertSame(2, $result['previous_count']);
        $this->assertSame(3, $result['current_count']);
        $this->assertSame(60.0, $result['percent']);
    }

    #[Test]
    public function it_returns_all_three_windows_and_marks_empty_ones_unavailable(): void
    {
        $result = $this->calculate([]);

        $this->assertSame(['7d', '30d', 'all'], array_keys($result));
        $this->assertFalse($result['7d']['available']);
        $this->assertFalse($result['30d']['available']);
        $this->assertFalse($result['all']['available']);
    }

    #[Test]
    public function it_ignores_sales_without_a_sold_at_date_for_all_time(): void
    {
        $result = $this->calculate([
            $this->sale('2026-01-01 12:00:00', 100),
            (new Sale([
                'sale_id' => 'null',
                'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
                'price' => 999,
                'sold_at' => null,
                'raw_json' => [],
            ])),
            $this->sale('2026-01-02 12:00:00', 100),
            $this->sale('2026-06-01 12:00:00', 160),
            $this->sale('2026-06-02 12:00:00', 160),
        ])['all'];

        $this->assertTrue($result['available']);
        $this->assertSame(100.0, $result['previous']);
        $this->assertSame(160.0, $result['current']);
    }
}
