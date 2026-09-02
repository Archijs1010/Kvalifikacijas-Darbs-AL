<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\TrackedSkin;
use App\Services\CSFloatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportSalesController extends Controller
{
    public function __invoke(CSFloatService $csfloat): JsonResponse
    {
        $imported = 0;
        $skipped = 0;
        $failedSkins = [];

        $skins = TrackedSkin::query()->where('enabled', true)->get();

        foreach ($skins as $skin) {
            try {
                $payload = $csfloat->getSales($skin->market_hash_name);
            } catch (Throwable) {
                $failedSkins[] = $skin->market_hash_name;
                continue;
            }

            $sales = is_array($payload)
                && array_key_exists('data', $payload)
                && is_array($payload['data'])
                    ? $payload['data']
                    : $payload;

            if (! is_array($sales)) {
                $failedSkins[] = $skin->market_hash_name;
                continue;
            }

            $existingSaleIds = Sale::query()
                ->where('market_hash_name', $skin->market_hash_name)
                ->pluck('sale_id');

            foreach ($sales as $entry) {
                if (! is_array($entry) || ! isset($entry['id'])) {
                    continue;
                }

                $saleId = (string) $entry['id'];

                if ($existingSaleIds->contains($saleId)) {
                    $skipped++;
                    continue;
                }

                Sale::create([
                    'sale_id' => $saleId,
                    'market_hash_name' => $skin->market_hash_name,
                    'price' => isset($entry['price']) ? round(((int) $entry['price']) / 100, 2) : 0,
                    'float_value' => $entry['float_value'] ?? null,
                    'sold_at' => $this->resolveSoldAt($entry['sold_at'] ?? null),
                    'paint_index' => isset($entry['paint_index']) ? (int) $entry['paint_index'] : null,
                    'raw_json' => $entry,
                ]);

                $existingSaleIds->push($saleId);
                $imported++;
            }
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'failed_skins' => $failedSkins,
        ]);
    }

    private function resolveSoldAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value)
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
