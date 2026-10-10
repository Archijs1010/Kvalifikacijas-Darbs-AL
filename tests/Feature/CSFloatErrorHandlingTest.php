<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\LoadsCSFloatFixtures;
use Tests\TestCase;

class CSFloatErrorHandlingTest extends TestCase
{
    use LoadsCSFloatFixtures;

    private function track(string $name = 'AK-47 | Redline (Field-Tested)'): TrackedSkin
    {
        return TrackedSkin::create([
            'market_hash_name' => $name,
            'enabled' => true,
        ]);
    }

    #[Test]
    public function it_reports_an_authentication_failure_for_a_401(): void
    {
        Http::fake(['csfloat.com/*' => Http::response(['error' => 'unauthorized'], 401)]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(502);
        $this->assertSame('auth', $response->json('category'));
        $this->assertFalse($response->json('rate_limited'));
        $this->assertStringContainsString('unauthorized', $response->json('message'));
        $this->assertSame(0, Sale::count());
    }

    #[Test]
    public function it_reports_an_authentication_failure_for_a_403(): void
    {
        Http::fake(['csfloat.com/*' => Http::response(['error' => 'forbidden'], 403)]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(502);
        $this->assertSame('auth', $response->json('category'));
        $this->assertStringContainsString('unauthorized', $response->json('message'));
    }

    #[Test]
    public function it_reports_a_404_as_a_distinct_error(): void
    {
        Http::fake(['csfloat.com/*' => Http::response('not found', 404)]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(502);
        $this->assertSame('not_found', $response->json('category'));
        $this->assertStringContainsString('could not find', $response->json('message'));
    }

    #[Test]
    public function it_reports_rate_limiting_with_the_retry_after_header(): void
    {
        Http::fake(['csfloat.com/*' => Http::response(['error' => 'rate limited'], 429, [
            'Retry-After' => '120',
            'x-ratelimit-limit' => '500',
            'x-ratelimit-remaining' => '0',
        ])]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(429);
        $this->assertSame('rate_limited', $response->json('category'));
        $this->assertTrue($response->json('rate_limited'));
        $this->assertSame(120, $response->json('retry_after'));
        $this->assertSame(500, $response->json('rate_limit'));
        $this->assertSame(0, $response->json('rate_remaining'));
        $this->assertStringContainsString('2 minutes', $response->json('message'));
    }

    #[Test]
    public function it_computes_the_retry_after_from_the_reset_header_when_retry_after_is_absent(): void
    {
        $reset = now()->addMinutes(3)->getTimestamp();

        Http::fake(['csfloat.com/*' => Http::response(null, 429, [
            'x-ratelimit-reset' => (string) $reset,
        ])]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(429);
        $this->assertSame('rate_limited', $response->json('category'));
        $this->assertEqualsWithDelta(180, $response->json('retry_after'), 5);
    }

    #[Test]
    public function it_reports_server_errors_for_5xx_responses(): void
    {
        Http::fake(['csfloat.com/*' => Http::response('boom', 503)]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(502);
        $this->assertSame('server_error', $response->json('category'));
        $this->assertStringContainsString('503', $response->json('message'));
    }

    #[Test]
    public function it_reports_connection_failures_and_still_records_the_attempt(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));

        $response->assertStatus(502);
        $this->assertSame('connection', $response->json('category'));
        $this->assertStringContainsString('Could not reach CSFloat', $response->json('message'));
        $this->assertDatabaseHas('api_requests', ['status' => 0]);
    }

    #[Test]
    public function it_does_not_retry_a_rate_limited_request_repeatedly(): void
    {
        Http::fake(['csfloat.com/*' => Http::response(null, 429)]);
        $skin = $this->track();

        $this->post(route('skins.import', $skin))->assertStatus(429);

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_bulk_import_surfaces_rate_limit_details_and_stops(): void
    {
        $this->track('AK-47 | Redline (Field-Tested)');
        $this->track('AWP | Dragon Lore (Factory New)');
        $this->track('M4A4 | Howl (Factory New)');

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), rawurlencode('AK-47 | Redline (Field-Tested)'))) {
                return Http::response(null, 429, [
                    'Retry-After' => '90',
                    'x-ratelimit-limit' => '500',
                    'x-ratelimit-remaining' => '0',
                ]);
            }

            return Http::response([], 200);
        });

        $response = $this->postJson(route('import-sales'));

        $response->assertOk()
            ->assertJson([
                'rate_limited' => true,
                'failed_skins' => ['AK-47 | Redline (Field-Tested)'],
                'not_attempted' => [
                    'AWP | Dragon Lore (Factory New)',
                    'M4A4 | Howl (Factory New)',
                ],
            ]);

        $this->assertSame(90, $response->json('rate_limit.retry_after'));
        $this->assertSame(500, $response->json('rate_limit.limit'));
        $this->assertSame(0, $response->json('rate_limit.remaining'));
        $this->assertStringContainsString('Try again in 1 minute 30s', $response->json('rate_limit.message'));

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_bulk_import_keeps_going_and_records_a_message_for_a_server_error(): void
    {
        $payloads = collect($this->csfloatSalesFixture())
            ->groupBy(fn (array $sale) => $sale['item']['market_hash_name'])
            ->map(fn ($group) => $group->values()->all())
            ->all();

        Http::fake(function (Request $request) use ($payloads) {
            if (str_contains($request->url(), rawurlencode('AK-47 | Redline (Field-Tested)'))) {
                return Http::response('boom', 500);
            }

            return Http::response($payloads['AWP | Dragon Lore (Factory New)'], 200);
        });

        $this->track('AK-47 | Redline (Field-Tested)');
        $this->track('AWP | Dragon Lore (Factory New)');

        $response = $this->postJson(route('import-sales'));

        $response->assertOk()
            ->assertJson([
                'successful_skins' => ['AWP | Dragon Lore (Factory New)'],
                'failed_skins' => ['AK-47 | Redline (Field-Tested)'],
                'rate_limited' => false,
                'rate_limit' => null,
            ]);

        $this->assertStringContainsString(
            '500',
            $response->json('errors.AK-47 | Redline (Field-Tested)'),
        );

        $this->assertSame(2, Sale::count());
    }

    #[Test]
    public function it_never_exposes_the_api_key_in_responses_or_pages(): void
    {
        config(['services.csfloat.key' => 'SENTINEL-FAKE-KEY']);

        Http::fake(['csfloat.com/*' => Http::response('boom', 500)]);
        $skin = $this->track();

        $response = $this->post(route('skins.import', $skin));
        $response->assertStatus(502);
        $this->assertStringNotContainsString('SENTINEL-FAKE-KEY', $response->getContent());

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertStringNotContainsString('SENTINEL-FAKE-KEY', $dashboard->getContent());
    }

    #[Test]
    public function the_test_command_reports_a_rate_limit_clearly(): void
    {
        Http::fake(['csfloat.com/*' => Http::response(null, 429, [
            'Retry-After' => '60',
            'x-ratelimit-limit' => '500',
            'x-ratelimit-remaining' => '0',
        ])]);

        $this->artisan('csfloat:test')
            ->expectsOutputToContain('rate limit')
            ->assertExitCode(0);
    }
}
