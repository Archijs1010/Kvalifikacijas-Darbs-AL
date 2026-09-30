<?php

use App\Http\Controllers\ImportSalesController;
use App\Models\Sale;
use App\Models\TrackedSkin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', [
        'totalSkins' => TrackedSkin::count(),
        'totalSales' => Sale::count(),
    ]);
})->name('dashboard');

Route::get('/skins', function () {
    return view('skins.index', [
        'skins' => TrackedSkin::orderBy('market_hash_name')->get(),
    ]);
})->name('skins.index');

Route::get('/sales', function (Request $request) {
    $query = Sale::query();

    if ($request->filled('skin')) {
        $query->where('market_hash_name', $request->string('skin'));
    }

    if ($request->filled('min_float')) {
        $query->where('float_value', '>=', $request->float('min_float'));
    }

    if ($request->filled('max_float')) {
        $query->where('float_value', '<=', $request->float('max_float'));
    }

    return view('sales.index', [
        'sales' => $query->orderByDesc('sold_at')->paginate(25)->withQueryString(),
        'skins' => TrackedSkin::orderBy('market_hash_name')->pluck('market_hash_name'),
        'filters' => $request->only(['skin', 'min_float', 'max_float']),
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
