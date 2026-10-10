<?php

namespace App\Services;

use App\Exceptions\CSFloatException;
use App\Models\ApiRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CSFloatService
{
    private const TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 10;

    public function getSales(string $marketHashName): array
    {
        $response = $this->salesResponse($marketHashName);

        if ($exception = CSFloatException::fromResponse($response)) {
            throw $exception;
        }

        return $response->json() ?? [];
    }

    public function salesResponse(string $marketHashName): Response
    {
        $url = 'https://csfloat.com/api/v1/history/'.rawurlencode($marketHashName).'/sales';

        try {
            $response = Http::withToken(config('services.csfloat.key'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $exception) {
            $this->record($marketHashName, 0);

            throw CSFloatException::connection();
        }

        $this->record($marketHashName, $response->status(), $response);

        return $response;
    }

    private function record(string $marketHashName, int $status, ?Response $response = null): void
    {
        try {
            ApiRequest::create([
                'market_hash_name' => $marketHashName,
                'status' => $status,
                'rate_limit' => $response ? $this->quotaHeader($response, 'x-ratelimit-limit') : null,
                'rate_remaining' => $response ? $this->quotaHeader($response, 'x-ratelimit-remaining') : null,
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
