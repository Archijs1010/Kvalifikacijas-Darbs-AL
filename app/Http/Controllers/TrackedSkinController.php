<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackedSkinNoteRequest;
use App\Http\Requests\TrackedSkinRequest;
use App\Models\Sale;
use App\Models\TrackedSkin;
use App\Services\PriceChangeCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackedSkinController extends Controller
{
    private const SORTS = [
        'favorites',
        'name',
        'latest_price',
        'average_price',
        'float',
        'sales_count',
        'latest_sale',
    ];

    public function __construct(private PriceChangeCalculator $priceChanges) {}

    public function index(Request $request): View
    {
        $favoritesOnly = $request->boolean('favorites');
        $sort = $this->resolveSort($request->input('sort'));
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $query = TrackedSkin::query()
            ->select(['tracked_skins.*'])
            ->leftJoinSub(
                $this->salesAggregates(),
                'sales_stats',
                fn ($join) => $join->on('sales_stats.market_hash_name', '=', 'tracked_skins.market_hash_name')
            )
            ->addSelect(['latest_price' => $this->latestPriceSubquery()])
            ->when($favoritesOnly, fn (Builder $query) => $query->where('tracked_skins.is_favorite', true));

        $this->applySort($query, $sort, $direction);

        $skins = $query->get();

        $priceChanges = $skins
            ->mapWithKeys(fn (TrackedSkin $skin) => [$skin->id => $this->priceChanges->forSkin($skin)])
            ->all();

        return view('skins.index', [
            'skins' => $skins,
            'favoritesOnly' => $favoritesOnly,
            'priceChanges' => $priceChanges,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    private function resolveSort(mixed $sort): string
    {
        return is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'favorites';
    }

    private function salesAggregates(): Builder
    {
        return Sale::query()
            ->selectRaw('market_hash_name')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('AVG(price) as average_price')
            ->selectRaw('AVG(float_value) as average_float')
            ->selectRaw('MAX(sold_at) as latest_sale_at')
            ->groupBy('market_hash_name');
    }

    private function latestPriceSubquery(): Builder
    {
        return Sale::query()
            ->select('price')
            ->whereColumn('sales.market_hash_name', 'tracked_skins.market_hash_name')
            ->orderByDesc('sold_at')
            ->limit(1);
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'favorites') {
            $query->orderByDesc('tracked_skins.is_favorite')
                ->orderBy('tracked_skins.market_hash_name', $direction);

            return;
        }

        $column = match ($sort) {
            'latest_price' => 'latest_price',
            'average_price' => 'sales_stats.average_price',
            'float' => 'sales_stats.average_float',
            'sales_count' => 'sales_stats.sales_count',
            'latest_sale' => 'sales_stats.latest_sale_at',
            default => 'tracked_skins.market_hash_name',
        };

        $query->orderByRaw("{$column} IS NULL")
            ->orderBy($column, $direction)
            ->orderBy('tracked_skins.market_hash_name');
    }

    public function store(TrackedSkinRequest $request): RedirectResponse
    {
        $skin = TrackedSkin::create($request->validated());

        return redirect()
            ->route('skins.index')
            ->with('status', "Now tracking {$skin->market_hash_name}.");
    }

    public function edit(TrackedSkin $skin): View
    {
        return view('skins.edit', [
            'skin' => $skin,
        ]);
    }

    public function update(TrackedSkinRequest $request, TrackedSkin $skin): RedirectResponse
    {
        $skin->update($request->validated());

        return redirect()
            ->route('skins.index')
            ->with('status', "Updated {$skin->market_hash_name}.");
    }

    public function destroy(TrackedSkin $skin): RedirectResponse
    {
        $name = $skin->market_hash_name;

        $skin->delete();

        return redirect()
            ->route('skins.index')
            ->with('status', "Stopped tracking {$name}.");
    }

    public function toggle(TrackedSkin $skin): RedirectResponse
    {
        $skin->update(['enabled' => ! $skin->enabled]);

        return redirect()
            ->route('skins.index')
            ->with('status', "{$skin->market_hash_name} is now ".($skin->enabled ? 'enabled' : 'disabled').'.');
    }

    public function favorite(TrackedSkin $skin): RedirectResponse
    {
        $skin->update(['is_favorite' => ! $skin->is_favorite]);

        return redirect()
            ->route('skins.index', request()->only('favorites'))
            ->with('status', $skin->is_favorite
                ? "Pinned {$skin->market_hash_name}."
                : "Unpinned {$skin->market_hash_name}.");
    }

    public function editNote(TrackedSkin $skin): View
    {
        return view('skins.note', [
            'skin' => $skin,
        ]);
    }

    public function updateNote(TrackedSkinNoteRequest $request, TrackedSkin $skin): RedirectResponse
    {
        $skin->update(['note' => $request->validated()['note'] ?? null]);

        return redirect()
            ->route('skins.index')
            ->with('status', "Saved note for {$skin->market_hash_name}.");
    }

    public function destroyNote(TrackedSkin $skin): RedirectResponse
    {
        $skin->update(['note' => null]);

        return redirect()
            ->route('skins.index')
            ->with('status', "Removed note for {$skin->market_hash_name}.");
    }
}
