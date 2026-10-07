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
     * Sales that fall outside the skin's configured float range or that carry
     * a different phase are never persisted, and sale ids already on disk are
     * never written twice.
     *
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     skipped_duplicates: int,
     *     skipped_out_of_range: int,
     *     skipped_wrong_phase: int,
     *     skipped_malformed: int,
     *     no_sales: bool,
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
            'skipped_wrong_phase' => 0,
            'skipped_malformed' => 0,
            'no_sales' => false,
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

        // CSFloat answers an unknown market hash name with HTTP 200 and an
        // empty list rather than a 404, so "nothing came back" has to be
        // reported distinctly from "nothing new to import".
        $totals['no_sales'] = $entries === [];

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

            if (! $this->matchesPhase($attributes['phase'], $skin)) {
                $totals['skipped']++;
                $totals['skipped_wrong_phase']++;

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

        $this->backfillItemAttributes($skin->market_hash_name);

        return $totals;
    }

    /**
     * Rows stored before paint_seed/phase existed still hold the complete
     * CSFloat payload in raw_json, so those values can be recovered locally
     * instead of waiting for CSFloat to return that sale again.
     */
    private function backfillItemAttributes(string $marketHashName): void
    {
        Sale::query()
            ->where('market_hash_name', $marketHashName)
            ->where(function ($query) {
                $query->whereNull('paint_seed')->orWhereNull('phase');
            })
            ->get()
            ->each(function (Sale $sale): void {
                $item = $sale->raw_json['item'] ?? [];
                $updates = [];

                if ($sale->paint_seed === null && is_numeric($item['paint_seed'] ?? null)) {
                    $updates['paint_seed'] = (int) $item['paint_seed'];
                }

                if ($sale->phase === null
                    && is_string($item['phase'] ?? null)
                    && trim($item['phase']) !== '') {
                    $updates['phase'] = trim($item['phase']);
                }

                if ($updates !== []) {
                    $sale->update($updates);
                }
            });
    }

    /**
     * A skin with no configured phase accepts every sale, including phaseless
     * items such as gloves. When a phase *is* configured, a sale without one
     * cannot satisfy it and is rejected rather than silently admitted.
     */
    private function matchesPhase(?string $phase, TrackedSkin $skin): bool
    {
        $wanted = $skin->phase !== null ? trim((string) $skin->phase) : null;

        if ($wanted === null || $wanted === '') {
            return true;
        }

        if ($phase === null) {
            return false;
        }

        return strcasecmp($phase, $wanted) === 0;
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
