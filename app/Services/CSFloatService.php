<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CSFloatService
{
    public function getSales(string $marketHashName): array
    {
        return $this->salesResponse($marketHashName)->throw()->json() ?? [];
    }

    public function salesResponse(string $marketHashName): Response
    {
        $url = 'https://csfloat.com/api/v1/history/'.rawurlencode($marketHashName).'/sales';

        return Http::withToken(config('services.csfloat.key'))
            ->acceptJson()
            ->get($url);
    }
}
