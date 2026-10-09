<?php

namespace Tests\Feature;

use App\Models\ApiRequest;
use App\Models\TrackedSkin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiUsageTest extends TestCase
{
    private function track(string $name): TrackedSkin
    {
        return TrackedSkin::create(['market_hash_name' => $name]);
    }

    #[Test]
    public function it_records_every_csfloat_call(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200)]);
        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))->assertOk();

        $this->assertDatabaseCount('api_requests', 1);

        $recorded = ApiRequest::sole();
        $this->assertSame($skin->market_hash_name, $recorded->market_hash_name);
        $this->assertSame(200, $recorded->status);
        $this->assertNotNull($recorded->requested_at);
    }

    #[Test]
    public function it_captures_the_rate_limit_headers(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200, [
            'x-ratelimit-limit' => '500',
            'x-ratelimit-remaining' => '449',
        ])]);
        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))->assertOk();

        $recorded = ApiRequest::sole();
        $this->assertSame(500, $recorded->rate_limit);
        $this->assertSame(449, $recorded->rate_remaining);
    }

    #[Test]
    public function it_records_a_rate_limited_call_instead_of_hiding_it(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 429)]);
        $this->track('★ Karambit | Doppler (Factory New)');
        $this->track('★ Sport Gloves | Vice (Field-Tested)');

        $this->post(route('import-sales'))
            ->assertOk()
            ->assertJsonPath('rate_limited', true);

        $this->assertDatabaseCount('api_requests', 1);
        $this->assertSame(429, ApiRequest::sole()->status);
    }

    #[Test]
    public function it_records_a_call_whose_response_lacked_quota_headers(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200)]);
        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))->assertOk();

        $recorded = ApiRequest::sole();
        $this->assertNull($recorded->rate_limit);
        $this->assertNull($recorded->rate_remaining);
    }

    #[Test]
    public function it_summarises_traffic_and_the_latest_known_quota(): void
    {
        ApiRequest::create([
            'market_hash_name' => 'Old skin',
            'status' => 200,
            'rate_limit' => 500,
            'rate_remaining' => 490,
            'requested_at' => Carbon::now()->subDays(2),
        ]);

        ApiRequest::create([
            'market_hash_name' => 'Today skin',
            'status' => 200,
            'rate_limit' => 500,
            'rate_remaining' => 480,
            'requested_at' => Carbon::now()->subMinutes(5),
        ]);

        $usage = ApiRequest::usage();

        $this->assertSame(1, $usage['today']);
        $this->assertSame(1, $usage['last_day']);
        $this->assertSame(2, $usage['total']);
        $this->assertSame(480, $usage['remaining']);
        $this->assertSame(500, $usage['limit']);
        $this->assertNotNull($usage['last_at']);
    }

    #[Test]
    public function it_shows_usage_and_recent_calls_on_the_dashboard(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200, [
            'x-ratelimit-limit' => '500',
            'x-ratelimit-remaining' => '411',
        ])]);
        $skin = $this->track('★ Karambit | Doppler (Factory New)');

        $this->post(route('skins.import', $skin))->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('CSFloat API usage')
            ->assertSee('Remaining quota')
            ->assertSee('411')
            ->assertSee('★ Karambit | Doppler (Factory New)');
    }

    #[Test]
    public function it_returns_a_fresh_usage_tally_from_the_bulk_import(): void
    {
        Http::fake(['csfloat.com/*' => Http::response([], 200, [
            'x-ratelimit-limit' => '500',
            'x-ratelimit-remaining' => '498',
        ])]);
        $this->track('★ Karambit | Doppler (Factory New)');
        $this->track('★ Sport Gloves | Vice (Field-Tested)');

        $this->post(route('import-sales'))
            ->assertOk()
            ->assertJsonPath('usage.total', 2)
            ->assertJsonPath('usage.today', 2)
            ->assertJsonPath('usage.remaining', 498)
            ->assertJsonPath('usage.limit', 500);
    }
}
