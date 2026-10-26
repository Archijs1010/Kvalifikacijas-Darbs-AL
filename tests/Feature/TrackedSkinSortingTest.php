<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackedSkinSortingTest extends TestCase
{
    private function skin(string $name, bool $favorite = false): TrackedSkin
    {
        return TrackedSkin::create([
            'market_hash_name' => $name,
            'enabled' => true,
            'is_favorite' => $favorite,
        ]);
    }

    private function sale(string $name, float $price, string $soldAt, float $float = 0.1): Sale
    {
        static $i = 0;
        $i++;

        return Sale::create([
            'sale_id' => 'sort'.$i,
            'market_hash_name' => $name,
            'price' => $price,
            'float_value' => $float,
            'sold_at' => $soldAt,
            'raw_json' => [],
        ]);
    }

    private function index(array $query = [])
    {
        return $this->get(route('skins.index', $query))->assertOk();
    }

    #[Test]
    public function it_defaults_to_favorites_first_then_name(): void
    {
        $this->skin('Bravo | Two (Minimal Wear)');
        $this->skin('Alpha | One (Factory New)', true);

        $this->index()->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);
    }

    #[Test]
    public function it_sorts_by_skin_name_ascending_and_descending(): void
    {
        $this->skin('Bravo | Two (Minimal Wear)');
        $this->skin('Alpha | One (Factory New)');
        $this->skin('Charlie | Three (Field-Tested)');

        $this->index(['sort' => 'name', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two', 'Charlie | Three']);

        $this->index(['sort' => 'name', 'direction' => 'desc'])
            ->assertSeeInOrder(['Charlie | Three', 'Bravo | Two', 'Alpha | One']);
    }

    #[Test]
    public function it_sorts_by_the_latest_sale_price(): void
    {
        $alpha = 'Alpha | One (Factory New)';
        $bravo = 'Bravo | Two (Minimal Wear)';

        $this->skin($alpha);
        $this->skin($bravo);

        $this->sale($alpha, 50, '2026-01-01 12:00:00');
        $this->sale($alpha, 150, '2026-03-01 12:00:00');
        $this->sale($bravo, 120, '2026-02-01 12:00:00');

        $this->index(['sort' => 'latest_price', 'direction' => 'asc'])
            ->assertSeeInOrder(['Bravo | Two', 'Alpha | One']);

        $this->index(['sort' => 'latest_price', 'direction' => 'desc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);
    }

    #[Test]
    public function it_sorts_by_the_average_sale_price(): void
    {
        $alpha = 'Alpha | One (Factory New)';
        $bravo = 'Bravo | Two (Minimal Wear)';

        $this->skin($alpha);
        $this->skin($bravo);

        $this->sale($alpha, 50, '2026-01-01 12:00:00');
        $this->sale($alpha, 150, '2026-03-01 12:00:00');
        $this->sale($bravo, 120, '2026-02-01 12:00:00');

        $this->index(['sort' => 'average_price', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);

        $this->index(['sort' => 'average_price', 'direction' => 'desc'])
            ->assertSeeInOrder(['Bravo | Two', 'Alpha | One']);
    }

    #[Test]
    public function it_sorts_by_the_average_sale_float(): void
    {
        $alpha = 'Alpha | One (Factory New)';
        $bravo = 'Bravo | Two (Minimal Wear)';

        $this->skin($alpha);
        $this->skin($bravo);

        $this->sale($alpha, 100, '2026-01-01 12:00:00', 0.1);
        $this->sale($alpha, 100, '2026-02-01 12:00:00', 0.3);
        $this->sale($bravo, 100, '2026-01-01 12:00:00', 0.5);

        $this->index(['sort' => 'float', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);

        $this->index(['sort' => 'float', 'direction' => 'desc'])
            ->assertSeeInOrder(['Bravo | Two', 'Alpha | One']);
    }

    #[Test]
    public function it_sorts_by_the_number_of_stored_sales(): void
    {
        $alpha = 'Alpha | One (Factory New)';
        $bravo = 'Bravo | Two (Minimal Wear)';

        $this->skin($alpha);
        $this->skin($bravo);

        $this->sale($alpha, 100, '2026-01-01 12:00:00');
        $this->sale($alpha, 100, '2026-01-02 12:00:00');
        $this->sale($alpha, 100, '2026-01-03 12:00:00');
        $this->sale($bravo, 100, '2026-01-01 12:00:00');

        $this->index(['sort' => 'sales_count', 'direction' => 'asc'])
            ->assertSeeInOrder(['Bravo | Two', 'Alpha | One']);

        $this->index(['sort' => 'sales_count', 'direction' => 'desc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);
    }

    #[Test]
    public function it_sorts_by_the_latest_sale_date(): void
    {
        $alpha = 'Alpha | One (Factory New)';
        $bravo = 'Bravo | Two (Minimal Wear)';

        $this->skin($alpha);
        $this->skin($bravo);

        $this->sale($alpha, 100, '2026-03-01 12:00:00');
        $this->sale($bravo, 100, '2026-05-01 12:00:00');

        $this->index(['sort' => 'latest_sale', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);

        $this->index(['sort' => 'latest_sale', 'direction' => 'desc'])
            ->assertSeeInOrder(['Bravo | Two', 'Alpha | One']);
    }

    #[Test]
    public function it_keeps_favorites_first_in_both_directions(): void
    {
        $this->skin('Bravo | Two (Minimal Wear)');
        $this->skin('Alpha | One (Factory New)', true);
        $this->skin('Charlie | Three (Field-Tested)', true);

        $this->index(['sort' => 'favorites', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Charlie | Three', 'Bravo | Two']);

        $this->index(['sort' => 'favorites', 'direction' => 'desc'])
            ->assertSeeInOrder(['Charlie | Three', 'Alpha | One', 'Bravo | Two']);
    }

    #[Test]
    public function name_sort_does_not_prioritise_favorites(): void
    {
        $this->skin('Alpha | One (Factory New)', true);
        $this->skin('Charlie | Three (Field-Tested)');

        $this->index(['sort' => 'name', 'direction' => 'desc'])
            ->assertSeeInOrder(['Charlie | Three', 'Alpha | One']);
    }

    #[Test]
    public function skins_without_sales_sort_last_for_sales_based_sorts(): void
    {
        $this->skin('Alpha | One (Factory New)');
        $this->skin('Zulu | No Sales (Factory New)');

        $this->sale('Alpha | One (Factory New)', 100, '2026-01-01 12:00:00');

        $this->index(['sort' => 'sales_count', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Zulu | No Sales']);

        $this->index(['sort' => 'sales_count', 'direction' => 'desc'])
            ->assertSeeInOrder(['Alpha | One', 'Zulu | No Sales']);

        $this->index(['sort' => 'latest_price', 'direction' => 'asc'])
            ->assertSeeInOrder(['Alpha | One', 'Zulu | No Sales']);
    }

    #[Test]
    public function it_falls_back_to_the_default_sort_for_unknown_values(): void
    {
        $this->skin('Bravo | Two (Minimal Wear)');
        $this->skin('Alpha | One (Factory New)', true);

        $this->index(['sort' => 'bogus', 'direction' => 'sideways'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two'])
            ->assertSee('value="favorites" selected', false)
            ->assertSee('value="asc" selected', false);
    }

    #[Test]
    public function it_falls_back_to_ascending_for_an_unknown_direction(): void
    {
        $this->skin('Bravo | Two (Minimal Wear)');
        $this->skin('Alpha | One (Factory New)');

        $this->index(['sort' => 'name', 'direction' => 'sideways'])
            ->assertSeeInOrder(['Alpha | One', 'Bravo | Two']);
    }

    #[Test]
    public function it_keeps_the_favorites_filter_and_sorting_together(): void
    {
        $this->skin('Alpha | One (Factory New)', true);
        $this->skin('Bravo | Two (Minimal Wear)');

        $this->sale('Alpha | One (Factory New)', 100, '2026-01-01 12:00:00');
        $this->sale('Bravo | Two (Minimal Wear)', 100, '2026-01-01 12:00:00');
        $this->sale('Bravo | Two (Minimal Wear)', 100, '2026-01-02 12:00:00');

        $this->index(['favorites' => 1, 'sort' => 'sales_count', 'direction' => 'desc'])
            ->assertSee('Alpha | One')
            ->assertDontSee('Bravo | Two');
    }

    #[Test]
    public function it_preserves_the_selected_sort_and_filter_in_the_controls(): void
    {
        $this->skin('Alpha | One (Factory New)', true);

        $this->index(['favorites' => 1, 'sort' => 'average_price', 'direction' => 'desc'])
            ->assertSee('name="favorites" value="1" checked', false)
            ->assertSee('value="average_price" selected', false)
            ->assertSee('value="desc" selected', false);
    }

    #[Test]
    public function it_makes_no_api_requests_when_sorting(): void
    {
        $this->skin('Alpha | One (Factory New)');
        $this->sale('Alpha | One (Factory New)', 100, '2026-01-01 12:00:00');

        $this->index(['sort' => 'average_price', 'direction' => 'desc'])->assertOk();

        Http::assertNothingSent();
    }
}
