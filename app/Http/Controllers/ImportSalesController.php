<?php

namespace App\Http\Controllers;

use App\Models\TrackedSkin;
use App\Services\SalesImporter;
use Illuminate\Http\JsonResponse;

class ImportSalesController extends Controller
{
    public function __construct(private readonly SalesImporter $importer) {}

    public function __invoke(): JsonResponse
    {
        $imported = 0;
        $skipped = 0;
        $malformed = 0;
        $outOfRange = 0;
        $failedSkins = [];

        $skins = TrackedSkin::query()->where('enabled', true)->get();

        foreach ($skins as $skin) {
            $result = $this->importer->importSkin($skin);

            if ($result['failed']) {
                $failedSkins[] = $skin->market_hash_name;

                continue;
            }

            $imported += $result['imported'];
            $skipped += $result['skipped_duplicates'];
            $malformed += $result['skipped_malformed'];
            $outOfRange += $result['skipped_out_of_range'];
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'malformed' => $malformed,
            'out_of_range' => $outOfRange,
            'failed_skins' => $failedSkins,
        ]);
    }
}
