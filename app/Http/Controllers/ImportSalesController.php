<?php

namespace App\Http\Controllers;

use App\Models\TrackedSkin;
use App\Services\SalesImporter;
use Illuminate\Http\JsonResponse;

/**
 * Imports every enabled tracked skin sequentially, in one request, without a
 * queue. A skin that fails does not stop the run; an exhausted rate limit does,
 * because continuing would only earn more 429s.
 */
class ImportSalesController extends Controller
{
    public function __construct(private readonly SalesImporter $importer) {}

    public function __invoke(): JsonResponse
    {
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $malformed = 0;
        $outOfRange = 0;
        $wrongPhase = 0;
        $successfulSkins = [];
        $failedSkins = [];
        $noSalesSkins = [];
        $rateLimited = false;
        $notAttempted = [];

        $skins = TrackedSkin::query()
            ->where('enabled', true)
            ->get()
            ->values();

        foreach ($skins as $index => $skin) {
            $result = $this->importer->importSkin($skin);

            if ($result['rate_limited']) {
                $rateLimited = true;
                $failedSkins[] = $skin->market_hash_name;
                $notAttempted = $skins->slice($index + 1)->pluck('market_hash_name')->all();

                break;
            }

            if ($result['failed']) {
                $failedSkins[] = $skin->market_hash_name;

                continue;
            }

            $successfulSkins[] = $skin->market_hash_name;

            if ($result['no_sales']) {
                $noSalesSkins[] = $skin->market_hash_name;
            }

            $imported += $result['imported'];
            $skipped += $result['skipped'];
            $duplicates += $result['skipped_duplicates'];
            $malformed += $result['skipped_malformed'];
            $outOfRange += $result['skipped_out_of_range'];
            $wrongPhase += $result['skipped_wrong_phase'];
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'malformed' => $malformed,
            'out_of_range' => $outOfRange,
            'wrong_phase' => $wrongPhase,
            'successful_skins' => $successfulSkins,
            'failed_skins' => $failedSkins,
            'no_sales_skins' => $noSalesSkins,
            'rate_limited' => $rateLimited,
            'not_attempted' => $notAttempted,
        ]);
    }
}
