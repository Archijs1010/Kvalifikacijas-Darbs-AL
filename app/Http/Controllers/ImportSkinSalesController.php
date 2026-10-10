<?php

namespace App\Http\Controllers;

use App\Models\TrackedSkin;
use App\Services\SalesImporter;
use Illuminate\Http\JsonResponse;

class ImportSkinSalesController extends Controller
{
    public function __construct(private readonly SalesImporter $importer) {}

    public function __invoke(TrackedSkin $skin): JsonResponse
    {
        $result = $this->importer->importSkin($skin);

        if ($result['failed']) {
            return response()->json([
                'message' => "Failed to fetch sales for {$skin->market_hash_name}.",
            ], 502);
        }

        return response()->json([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'skipped_duplicates' => $result['skipped_duplicates'],
            'skipped_out_of_range' => $result['skipped_out_of_range'],
            'skipped_wrong_phase' => $result['skipped_wrong_phase'],
            'skipped_malformed' => $result['skipped_malformed'],
            'no_sales' => $result['no_sales'],
        ]);
    }
}
