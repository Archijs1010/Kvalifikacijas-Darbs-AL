<?php

namespace App\Http\Controllers;

use App\Models\TrackedSkin;
use App\Services\SalesImporter;
use Illuminate\Http\JsonResponse;

/**
 * Manual, one skin at a time import driven by the button on the skins index.
 */
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
            'skipped_malformed' => $result['skipped_malformed'],
        ]);
    }
}
