<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CSFloatService
{
    public function getSales(string $marketHashName): array
    {
        $url = 'https://api.csfloat.com/api/v1/history/'.rawurlencode($marketHashName).'/sales';

        $response = Http::withToken(config('services.csfloat.key'))
            ->acceptJson()
            ->get($url);

        return $response->throw()->json() ?? [];
    }
}
