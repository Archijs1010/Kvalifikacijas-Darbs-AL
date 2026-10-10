<?php

namespace App\Http\Controllers;

use App\Models\ApiRequest;
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
        $duplicates = 0;
        $malformed = 0;
        $outOfRange = 0;
        $wrongPhase = 0;
        $successfulSkins = [];
        $failedSkins = [];
        $noSalesSkins = [];
        $errors = [];
        $rateLimited = false;
        $rateLimit = null;
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
                $errors[$skin->market_hash_name] = $result['error_message'];
                $rateLimit = [
                    'message' => $result['error_message'],
                    'retry_after' => $result['retry_after'],
                    'limit' => $result['rate_limit'],
                    'remaining' => $result['rate_remaining'],
                ];
                $notAttempted = $skins->slice($index + 1)->pluck('market_hash_name')->all();

                break;
            }

            if ($result['failed']) {
                $failedSkins[] = $skin->market_hash_name;
                $errors[$skin->market_hash_name] = $result['error_message'];

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
            'errors' => $errors,
            'no_sales_skins' => $noSalesSkins,
            'rate_limited' => $rateLimited,
            'rate_limit' => $rateLimit,
            'not_attempted' => $notAttempted,
            'usage' => ApiRequest::usage(),
        ]);
    }
}
