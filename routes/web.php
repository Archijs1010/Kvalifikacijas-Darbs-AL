<?php

use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\ImportSkinSalesController;
use App\Http\Controllers\TrackedSkinController;
use App\Models\ApiRequest;
use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', [
        'totalSkins' => TrackedSkin::count(),
        'totalSales' => Sale::count(),
        // Local tally of outbound calls, so the remaining CSFloat quota can be
        // read without spending another request to ask for it.
        'apiUsage' => ApiRequest::usage(),
        'recentApiRequests' => ApiRequest::query()->orderByDesc('requested_at')->limit(10)->get(),
    ]);
})->name('dashboard');

Route::get('/skins', [TrackedSkinController::class, 'index'])->name('skins.index');
Route::post('/skins', [TrackedSkinController::class, 'store'])->name('skins.store');
Route::get('/skins/{skin}/edit', [TrackedSkinController::class, 'edit'])->name('skins.edit');
Route::put('/skins/{skin}', [TrackedSkinController::class, 'update'])->name('skins.update');

Route::get('/skins/{skin}/sales', function (TrackedSkin $skin) {
    $stored = Sale::query()
        ->where('market_hash_name', $skin->market_hash_name)
        ->orderBy('sold_at')
        ->get(['sold_at', 'price']);

    $prices = $stored->pluck('price')->map(fn ($price) => (float) $price);

    return view('skins.sales', [
        'skin' => $skin,
        'stats' => [
            'count' => $prices->count(),
            'lowest' => $prices->min(),
            'highest' => $prices->max(),
            'average' => $prices->avg(),
            'median' => $prices->median(),
        ],
        // Every stored sale travels with the page so the range selector can
        // redraw the chart client-side without asking CSFloat for anything.
        'chart' => $stored
            ->filter(fn (Sale $sale) => $sale->sold_at !== null)
            ->map(fn (Sale $sale) => [
                'date' => $sale->sold_at->format('Y-m-d H:i:s'),
                'price' => (float) $sale->price,
            ])
            ->values(),
        'sales' => Sale::query()
            ->where('market_hash_name', $skin->market_hash_name)
            ->orderByDesc('sold_at')
            ->paginate(25),
    ]);
})->name('skins.sales');

Route::patch('/skins/{skin}/toggle', [TrackedSkinController::class, 'toggle'])->name('skins.toggle');
Route::post('/skins/{skin}/import', ImportSkinSalesController::class)->name('skins.import');
Route::delete('/skins/{skin}', [TrackedSkinController::class, 'destroy'])->name('skins.destroy');

Route::get('/sales', function (Request $request) {
    $query = Sale::query();

    if ($request->filled('skin')) {
        $query->where('market_hash_name', $request->string('skin'));
    }

    if ($request->filled('phase')) {
        $query->whereRaw('lower(phase) = ?', [mb_strtolower($request->string('phase'))]);
    }

    $ranges = [
        'min_float' => ['float_value', '>='],
        'max_float' => ['float_value', '<='],
        'min_price' => ['price', '>='],
        'max_price' => ['price', '<='],
    ];

    foreach ($ranges as $key => [$column, $operator]) {
        $value = $request->input($key);

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            $query->where($column, $operator, (float) $value);
        }
    }

    $from = $request->input('from');
    if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $query->where('sold_at', '>=', $from.' 00:00:00');
    }

    $to = $request->input('to');
    if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $query->where('sold_at', '<=', $to.' 23:59:59');
    }

    return view('sales.index', [
        'sales' => $query->orderByDesc('sold_at')->paginate(25)->withQueryString(),
        'skins' => TrackedSkin::orderBy('market_hash_name')->pluck('market_hash_name'),
        'phases' => Sale::query()->whereNotNull('phase')->distinct()->orderBy('phase')->pluck('phase'),
        'filters' => $request->only([
            'skin',
            'min_float',
            'max_float',
            'min_price',
            'max_price',
            'phase',
            'from',
            'to',
        ]),
    ]);
})->name('sales.index');

Route::get('/analytics', function (Request $request) {
    $skins = TrackedSkin::orderBy('market_hash_name')->pluck('market_hash_name');

    $selected = in_array($request->input('skin'), $skins->all(), true)
        ? $request->input('skin')
        : $skins->first();

    $query = Sale::query()->whereNotNull('sold_at')->orderBy('sold_at');

    if ($selected) {
        $query->where('market_hash_name', $selected);
    }

    $points = $query->get(['sold_at', 'price']);
    $prices = $points->map(fn (Sale $sale) => (float) $sale->price);

    return view('analytics.index', [
        'skins' => $skins,
        'selected' => $selected,
        'chart' => [
            'labels' => $points->map(fn (Sale $sale) => $sale->sold_at?->format('M j, Y'))->all(),
            'prices' => $prices->all(),
        ],
        'stats' => [
            'average' => $prices->isNotEmpty() ? round($prices->avg(), 2) : 0,
            'highest' => $prices->max() ?? 0,
            'lowest' => $prices->min() ?? 0,
            'total' => $points->count(),
        ],
    ]);
})->name('analytics.index');

Route::post('/import-sales', ImportSalesController::class)->name('import-sales');
