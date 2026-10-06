<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Throwable;

class SalesImporter
{
    public function __construct(
        private readonly CSFloatService $csfloat,
        private readonly CSFloatSaleMapper $mapper,
    ) {}

    /**
     * Fetch, map and persist the sales for a single tracked skin.
     *
     * Sales whose float falls outside the skin's configured range are never
     * persisted, and sale ids already on disk are never written twice.
     *
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     skipped_duplicates: int,
     *     skipped_out_of_range: int,
     *     skipped_malformed: int,
     *     failed: bool
     * }
     */
    public function importSkin(TrackedSkin $skin): array
    {
        $totals = [
            'imported' => 0,
            'skipped' => 0,
            'skipped_duplicates' => 0,
            'skipped_out_of_range' => 0,
            'skipped_malformed' => 0,
            'failed' => false,
        ];

        try {
            $entries = $this->mapper->salesList(
                $this->csfloat->getSales($skin->market_hash_name)
            );
        } catch (Throwable) {
            $totals['failed'] = true;

            return $totals;
        }

        $existingSaleIds = Sale::query()
            ->where('market_hash_name', $skin->market_hash_name)
            ->pluck('sale_id')
            ->flip();

        foreach ($entries as $entry) {
            $attributes = $this->mapper->map($entry, $skin->market_hash_name);

            if ($attributes === null
                || $attributes['price'] === null
                || $attributes['market_hash_name'] === null) {
                $totals['skipped']++;
                $totals['skipped_malformed']++;

                continue;
            }

            if ($existingSaleIds->has($attributes['sale_id'])) {
                $totals['skipped']++;
                $totals['skipped_duplicates']++;

                continue;
            }

            if (! $this->withinRange($attributes['float_value'], $skin)) {
                $totals['skipped']++;
                $totals['skipped_out_of_range']++;

                continue;
            }

            Sale::create($attributes);

            $existingSaleIds->put($attributes['sale_id'], true);
            $totals['imported']++;
        }

        return $totals;
    }

    /**
     * A skin with no configured bounds accepts every sale. When a bound *is*
     * configured, a sale without a float value cannot be confirmed as in range
     * and is therefore rejected rather than silently admitted.
     */
    private function withinRange(?float $value, TrackedSkin $skin): bool
    {
        $min = $skin->min_float !== null ? (float) $skin->min_float : null;
        $max = $skin->max_float !== null ? (float) $skin->max_float : null;

        if ($min === null && $max === null) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        if ($min !== null && $value < $min) {
            return false;
        }

        return ! ($max !== null && $value > $max);
    }
}
