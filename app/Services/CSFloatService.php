<?php

namespace App\Services;

use App\Models\ApiRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CSFloatService
{
    public function getSales(string $marketHashName): array
    {
        return $this->salesResponse($marketHashName)->throw()->json() ?? [];
    }

    public function salesResponse(string $marketHashName): Response
    {
        $url = 'https://csfloat.com/api/v1/history/'.rawurlencode($marketHashName).'/sales';

        $response = Http::withToken(config('services.csfloat.key'))
            ->acceptJson()
            ->get($url);

        $this->record($marketHashName, $response);

        return $response;
    }

    private function record(string $marketHashName, Response $response): void
    {
        try {
            ApiRequest::create([
                'market_hash_name' => $marketHashName,
                'status' => $response->status(),
                'rate_limit' => $this->quotaHeader($response, 'x-ratelimit-limit'),
                'rate_remaining' => $this->quotaHeader($response, 'x-ratelimit-remaining'),
                'requested_at' => now(),
            ]);
        } catch (Throwable) {
            // Never let a logging problem interrupt an import.
        }
    }

    private function quotaHeader(Response $response, string $header): ?int
    {
        $value = $response->header($header);

        return is_numeric($value) ? (int) $value : null;
    }
}
