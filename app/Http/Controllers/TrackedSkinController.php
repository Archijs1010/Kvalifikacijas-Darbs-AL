<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackedSkinRequest;
use App\Models\TrackedSkin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackedSkinController extends Controller
{
    public function index(Request $request): View
    {
        $favoritesOnly = $request->boolean('favorites');

        $skins = TrackedSkin::query()
            ->when($favoritesOnly, fn ($query) => $query->where('is_favorite', true))
            ->orderByDesc('is_favorite')
            ->orderBy('market_hash_name')
            ->get();

        return view('skins.index', [
            'skins' => $skins,
            'favoritesOnly' => $favoritesOnly,
        ]);
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
}
