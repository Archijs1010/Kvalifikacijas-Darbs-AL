<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PriceChangeCalculator
{
    public const MIN_ALL_TIME_SALES = 4;

    public function forSkin(TrackedSkin $skin, ?Carbon $now = null): array
    {
        $sales = Sale::query()
            ->where('market_hash_name', $skin->market_hash_name)
            ->whereNotNull('sold_at')
            ->get(['price', 'sold_at']);

        return $this->calculate($sales, $now);
    }

    public function calculate(Collection $sales, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return [
            '7d' => $this->window($sales, $now, 7),
            '30d' => $this->window($sales, $now, 30),
            'all' => $this->allTime($sales),
        ];
    }

    public function window(Collection $sales, Carbon $now, int $days): array
    {
        $currentStart = $now->copy()->subDays($days);
        $previousStart = $now->copy()->subDays($days * 2);

        $current = $sales->filter(
            fn (Sale $sale) => $sale->sold_at !== null
                && $sale->sold_at->greaterThanOrEqualTo($currentStart)
                && $sale->sold_at->lessThanOrEqualTo($now)
        );

        $previous = $sales->filter(
            fn (Sale $sale) => $sale->sold_at !== null
                && $sale->sold_at->greaterThanOrEqualTo($previousStart)
                && $sale->sold_at->lessThan($currentStart)
        );

        return $this->compare($previous, $current);
    }

    public function allTime(Collection $sales): array
    {
        $sorted = $sales
            ->filter(fn (Sale $sale) => $sale->sold_at !== null)
            ->sortBy(fn (Sale $sale) => $sale->sold_at->getTimestamp())
            ->values();

        if ($sorted->count() < self::MIN_ALL_TIME_SALES) {
            return $this->unavailable();
        }

        $half = intdiv($sorted->count(), 2);

        return $this->compare($sorted->slice(0, $half), $sorted->slice($half));
    }

    private function compare(Collection $previous, Collection $current): array
    {
        if ($previous->isEmpty() || $current->isEmpty()) {
            return $this->unavailable();
        }

        $previousAverage = $previous->avg(fn (Sale $sale) => (float) $sale->price);
        $currentAverage = $current->avg(fn (Sale $sale) => (float) $sale->price);

        if ($previousAverage <= 0.0) {
            return $this->unavailable();
        }

        return [
            'available' => true,
            'percent' => round(($currentAverage - $previousAverage) / $previousAverage * 100, 2),
            'previous' => round($previousAverage, 2),
            'current' => round($currentAverage, 2),
            'previous_count' => $previous->count(),
            'current_count' => $current->count(),
        ];
    }

    private function unavailable(): array
    {
        return [
            'available' => false,
            'percent' => null,
            'previous' => null,
            'current' => null,
            'previous_count' => 0,
            'current_count' => 0,
        ];
    }
}
