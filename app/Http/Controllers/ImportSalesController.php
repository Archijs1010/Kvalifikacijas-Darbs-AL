<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\TrackedSkin;
use App\Services\CSFloatSaleMapper;
use App\Services\CSFloatService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportSalesController extends Controller
{
    public function __construct(private readonly CSFloatSaleMapper $mapper) {}

    public function __invoke(CSFloatService $csfloat): JsonResponse
    {
        $imported = 0;
        $skipped = 0;
        $malformed = 0;
        $failedSkins = [];

        $skins = TrackedSkin::query()->where('enabled', true)->get();

        foreach ($skins as $skin) {
            try {
                $sales = $this->mapper->salesList($csfloat->getSales($skin->market_hash_name));
            } catch (Throwable) {
                $failedSkins[] = $skin->market_hash_name;

                continue;
            }

            $existingSaleIds = Sale::query()
                ->where('market_hash_name', $skin->market_hash_name)
                ->pluck('sale_id');

            foreach ($sales as $entry) {
                $attributes = $this->mapper->map($entry, $skin->market_hash_name);

                if ($attributes === null) {
                    $malformed++;

                    continue;
                }

                if ($attributes['price'] === null || $attributes['market_hash_name'] === null) {
                    $malformed++;

                    continue;
                }

                if ($existingSaleIds->contains($attributes['sale_id'])) {
                    $skipped++;

                    continue;
                }

                Sale::create($attributes);

                $existingSaleIds->push($attributes['sale_id']);
                $imported++;
            }
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'malformed' => $malformed,
            'failed_skins' => $failedSkins,
        ]);
    }
}
