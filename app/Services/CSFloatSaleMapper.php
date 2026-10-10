<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class CSFloatSaleMapper
{
    public function map(array $entry, ?string $fallbackMarketHashName = null): ?array
    {
        $saleId = $this->saleId($entry);

        if ($saleId === null) {
            return null;
        }

        $item = $this->item($entry);

        return [
            'sale_id' => $saleId,
            'market_hash_name' => $this->marketHashName($item, $fallbackMarketHashName),
            'price' => $this->price($entry),
            'float_value' => $this->floatValue($item),
            'sold_at' => $this->soldAt($entry),
            'paint_index' => $this->paintIndex($item),
            'paint_seed' => $this->paintSeed($item),
            'phase' => $this->phase($item),
            'raw_json' => $entry,
        ];
    }

    public function salesList(array $payload): array
    {
        $sales = $payload['data'] ?? $payload;

        if (! is_array($sales)) {
            return [];
        }

        return array_values(array_filter($sales, 'is_array'));
    }

    private function saleId(array $entry): ?string
    {
        $id = $entry['id'] ?? null;

        if (is_int($id)) {
            return (string) $id;
        }

        if (is_string($id) && trim($id) !== '') {
            return $id;
        }

        return null;
    }

    private function item(array $entry): array
    {
        $item = $entry['item'] ?? null;

        return is_array($item) ? $item : [];
    }

    private function marketHashName(array $item, ?string $fallback): ?string
    {
        $name = $item['market_hash_name'] ?? null;

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        if ($fallback !== null && trim($fallback) !== '') {
            return $fallback;
        }

        return null;
    }

    private function price(array $entry): ?float
    {
        $price = $entry['price'] ?? null;

        if (is_string($price) && is_numeric($price)) {
            $price = (float) $price;
        }

        if (! is_int($price) && ! is_float($price)) {
            return null;
        }

        return round(((float) $price) / 100, 2);
    }

    private function floatValue(array $item): ?float
    {
        $float = $item['float_value'] ?? null;

        if (is_string($float) && is_numeric($float)) {
            $float = (float) $float;
        }

        if (! is_int($float) && ! is_float($float)) {
            return null;
        }

        return (float) $float;
    }

    private function soldAt(array $entry): ?Carbon
    {
        $soldAt = $entry['sold_at'] ?? null;

        if ($soldAt === null || $soldAt === '') {
            return null;
        }

        try {
            return is_numeric($soldAt)
                ? Carbon::createFromTimestampUTC((int) $soldAt)
                : Carbon::parse((string) $soldAt);
        } catch (Throwable) {
            return null;
        }
    }

    private function paintIndex(array $item): ?int
    {
        $paintIndex = $item['paint_index'] ?? null;

        if (is_string($paintIndex) && is_numeric($paintIndex)) {
            $paintIndex = (int) $paintIndex;
        }

        if (! is_int($paintIndex)) {
            return null;
        }

        return $paintIndex;
    }

    private function paintSeed(array $item): ?int
    {
        $seed = $item['paint_seed'] ?? null;

        if (is_string($seed) && is_numeric($seed)) {
            $seed = (int) $seed;
        }

        if (! is_int($seed)) {
            return null;
        }

        return $seed;
    }

    private function phase(array $item): ?string
    {
        $phase = $item['phase'] ?? null;

        if (! is_string($phase)) {
            return null;
        }

        $phase = trim($phase);

        return $phase === '' ? null : $phase;
    }
}
