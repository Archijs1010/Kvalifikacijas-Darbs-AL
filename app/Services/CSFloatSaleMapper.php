<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class CSFloatSaleMapper
{
    /**
     * Map a single raw CSFloat sale entry onto the sales table columns.
     *
     * Field locations are taken from the live GET
     * /api/v1/history/{market_hash_name}/sales payload, where the
     * sale envelope is flat but all item attributes are nested
     * under "item".
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null null when the entry carries no sale id
     */
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

    /**
     * CSFloat returns a bare JSON array of sales. Older/other responses
     * wrap the list in a "data" key, so both shapes are accepted.
     *
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public function salesList(array $payload): array
    {
        $sales = $payload['data'] ?? $payload;

        if (! is_array($sales)) {
            return [];
        }

        return array_values(array_filter($sales, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
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

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function item(array $entry): array
    {
        $item = $entry['item'] ?? null;

        return is_array($item) ? $item : [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
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

    /**
     * CSFloat reports prices in integer cents.
     *
     * @param  array<string, mixed>  $entry
     */
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

    /**
     * @param  array<string, mixed>  $item
     */
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

    /**
     * @param  array<string, mixed>  $entry
     */
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

    /**
     * Not every CSFloat item is painted, so this stays nullable.
     *
     * @param  array<string, mixed>  $item
     */
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

    /**
     * The pattern seed belongs to the individual item, not the finish, so
     * two skins sharing a market hash name still differ by this value. It is
     * what decides how pattern-driven finishes (Case Hardened, Doppler)
     * actually read on screen.
     */
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

    /**
     * Doppler-style finishes carry a phase ("Phase 4", "Sapphire", "Ruby"),
     * which is not part of the market hash name and is only present on items
     * that actually have one — gloves and rifles omit it entirely.
     */
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
