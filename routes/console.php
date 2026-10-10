<?php

use App\Exceptions\CSFloatException;
use App\Services\CSFloatService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('csfloat:test', function (CSFloatService $csfloat) {
    $marketHashName = 'AK-47 | Redline (Field-Tested)';

    $this->line("Testing: {$marketHashName}");

    try {
        $response = $csfloat->salesResponse($marketHashName);
    } catch (CSFloatException $exception) {
        $this->error($exception->getMessage());

        return;
    }

    $this->info('HTTP status: '.$response->status());

    if ($exception = CSFloatException::fromResponse($response)) {
        $this->error($exception->getMessage());

        if ($exception->isRateLimited() && $exception->retryAfter !== null) {
            $this->warn('Retry after: '.$exception->retryAfter.' seconds');
        }

        if ($exception->rateRemaining !== null) {
            $this->warn('Remaining quota: '.$exception->rateRemaining.' / '.$exception->rateLimit);
        }

        return;
    }

    $payload = $response->json();

    if ($payload === null) {
        $this->error('No JSON payload returned.');

        return;
    }

    $sales = is_array($payload) && array_key_exists('data', $payload)
        ? $payload['data']
        : $payload;

    if (! is_array($sales)) {
        $sales = [];
    }

    $this->line('Sales returned: '.count($sales));

    $first = $sales[0] ?? null;

    if ($first === null) {
        $this->warn('No sales to display.');

        return;
    }

    $this->line(json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Test the CSFloatService integration');
