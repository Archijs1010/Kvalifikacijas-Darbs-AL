@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Tracked Skins</h1>

        <form method="GET" action="{{ route('skins.index') }}"
              class="flex flex-wrap items-end gap-3">
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="checkbox" name="favorites" value="1" @checked($favoritesOnly)
                       onchange="this.form.submit()"
                       class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500">
                Favorites only
            </label>

            <div class="flex flex-col gap-1">
                <label for="sort" class="text-xs uppercase tracking-wide text-zinc-500">Sort by</label>
                <select id="sort" name="sort" onchange="this.form.submit()"
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1 text-sm text-zinc-300 focus:border-sky-500 focus:outline-none">
                    <option value="favorites" @selected($sort === 'favorites')>Favorites first</option>
                    <option value="name" @selected($sort === 'name')>Skin name</option>
                    <option value="latest_price" @selected($sort === 'latest_price')>Latest price</option>
                    <option value="average_price" @selected($sort === 'average_price')>Average price</option>
                    <option value="float" @selected($sort === 'float')>Float</option>
                    <option value="sales_count" @selected($sort === 'sales_count')>Stored sales</option>
                    <option value="latest_sale" @selected($sort === 'latest_sale')>Latest sale date</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="direction" class="text-xs uppercase tracking-wide text-zinc-500">Direction</label>
                <select id="direction" name="direction" onchange="this.form.submit()"
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1 text-sm text-zinc-300 focus:border-sky-500 focus:outline-none">
                    <option value="asc" @selected($direction === 'asc')>Ascending</option>
                    <option value="desc" @selected($direction === 'desc')>Descending</option>
                </select>
            </div>

            <noscript>
                <button type="submit"
                        class="rounded-lg border border-zinc-700 px-3 py-1 text-xs text-zinc-300 hover:text-sky-400">
                    Apply
                </button>
            </noscript>
        </form>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <h2 class="text-sm font-medium">Add a skin</h2>

        <form method="POST" action="{{ route('skins.store') }}"
              class="mt-4 grid gap-4 sm:grid-cols-[1fr_7rem_7rem_8rem_auto_auto] sm:items-end">
            @csrf
            @include('skins._phase-options')

            <div class="flex flex-col gap-1">
                <label for="market_hash_name" class="text-xs uppercase tracking-wide text-zinc-500">Market hash name</label>
                <input id="market_hash_name" name="market_hash_name" type="text" required
                       value="{{ old('market_hash_name') }}"
                       placeholder="AK-47 | Redline (Field-Tested)"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('market_hash_name')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="min_float" class="text-xs uppercase tracking-wide text-zinc-500">Min float</label>
                <input id="min_float" name="min_float" type="number" min="0" max="1" step="0.0001"
                       value="{{ old('min_float') }}"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('min_float')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="max_float" class="text-xs uppercase tracking-wide text-zinc-500">Max float</label>
                <input id="max_float" name="max_float" type="number" min="0" max="1" step="0.0001"
                       value="{{ old('max_float') }}"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('max_float')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="phase" class="text-xs uppercase tracking-wide text-zinc-500">Phase</label>
                <input id="phase" name="phase" type="text" list="phase-options"
                       value="{{ old('phase') }}"
                       placeholder="Any"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('phase')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', true))
                       class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500">
                Track this skin
            </label>

            <button type="submit"
                    class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                Add skin
            </button>

            <p class="text-xs text-zinc-500 sm:col-span-6">
                Must match the CSFloat name exactly, including the star
                (<span class="text-zinc-300">★</span>) and the wear suffix.
                Example: <span class="text-zinc-300">★ Karambit | Doppler (Factory New)</span>
                or <span class="text-zinc-300">AK-47 | Redline (Field-Tested)</span>.
            </p>
        </form>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-zinc-800 text-left text-xs uppercase tracking-wide text-zinc-500">
                <th class="px-5 py-3 font-medium">Skin</th>
                <th class="px-5 py-3 font-medium">Min Float</th>
                <th class="px-5 py-3 font-medium">Max Float</th>
                <th class="px-5 py-3 font-medium">Phase</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium">Price change</th>
                <th class="px-5 py-3 font-medium">Import</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($skins as $skin)
                <tr class="border-b border-zinc-800/60 last:border-0">
                    <td class="px-5 py-3">
                        <a href="{{ route('skins.sales', $skin) }}" class="hover:text-sky-400">{{ $skin->market_hash_name }}</a>
                        @if ($skin->note)
                            <p class="mt-0.5 max-w-xs truncate text-xs text-zinc-500" title="{{ $skin->note }}">{{ $skin->note }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-zinc-400">{{ $skin->min_float ?? '—' }}</td>
                    <td class="px-5 py-3 text-zinc-400">{{ $skin->max_float ?? '—' }}</td>
                    <td class="px-5 py-3 text-zinc-400">{{ $skin->phase ?? '—' }}</td>
                    <td class="px-5 py-3">
                        @if ($skin->enabled)
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs text-emerald-400">Enabled</span>
                        @else
                            <span class="rounded-full bg-zinc-700/40 px-2.5 py-0.5 text-xs text-zinc-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 align-top">
                        <div class="space-y-2">
                            @include('skins._price-change', ['label' => '7d', 'change' => $priceChanges[$skin->id]['7d']])
                            @include('skins._price-change', ['label' => '30d', 'change' => $priceChanges[$skin->id]['30d']])
                        </div>
                    </td>
                    <td class="px-5 py-3 align-top">
                        <button type="button"
                                class="js-import text-sm text-zinc-400 hover:text-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                                data-url="{{ route('skins.import', $skin) }}">
                            Import
                        </button>
                        <span class="js-import-status mt-1 block max-w-[12rem] text-xs leading-snug break-words"></span>
                    </td>

                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-4">
                            <form method="POST" action="{{ route('skins.favorite', $skin) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        title="{{ $skin->is_favorite ? 'Unpin' : 'Pin' }}"
                                        aria-label="{{ $skin->is_favorite ? 'Unpin' : 'Pin' }} {{ $skin->market_hash_name }}"
                                        class="text-lg leading-none {{ $skin->is_favorite ? 'text-amber-400' : 'text-zinc-600 hover:text-amber-400' }}">
                                    {{ $skin->is_favorite ? '★' : '☆' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('skins.toggle', $skin) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="text-sm text-zinc-400 hover:text-sky-400">
                                    {{ $skin->enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>

                            <a href="{{ route('skins.edit', $skin) }}"
                               class="text-sm text-zinc-400 hover:text-sky-400">Edit</a>

                            <a href="{{ route('skins.note.edit', $skin) }}"
                               class="text-sm text-zinc-400 hover:text-sky-400">{{ $skin->note ? 'Edit note' : 'Add note' }}</a>

                            <form method="POST" action="{{ route('skins.destroy', $skin) }}"
                                  onsubmit="return confirm('Stop tracking {{ $skin->market_hash_name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-sm text-zinc-400 hover:text-red-400">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-zinc-500">No tracked skins yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.js-import').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const status = btn.nextElementSibling;
                const setStatus = (text, color, title = '') => {
                    ['text-zinc-500', 'text-zinc-400', 'text-red-400', 'text-amber-400', 'text-emerald-400']
                        .forEach((name) => status.classList.remove(name));
                    status.classList.add(color);
                    status.textContent = text;
                    status.title = title;
                };

                btn.disabled = true;
                btn.textContent = 'Importing…';
                setStatus('', 'text-zinc-500');

                try {
                    const res = await fetch(btn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();

                    if (!res.ok) {
                        setStatus(
                            data.message || 'Import failed.',
                            data.rate_limited ? 'text-amber-400' : 'text-red-400',
                            data.category === 'auth'
                                ? 'The server-side CSFloat API key was rejected.'
                                : ''
                        );
                        return;
                    }

                    if (data.no_sales) {
                        setStatus(
                            'No sales found for this name',
                            'text-amber-400',
                            'CSFloat has no sales recorded for this exact market hash '
                                + 'name. Check the star (★) and the wear suffix — they must match exactly.'
                        );
                    } else {
                        const parts = [`Imported ${data.imported}`];
                        if (data.skipped_wrong_phase) parts.push(`${data.skipped_wrong_phase} wrong phase`);
                        if (data.skipped_out_of_range) parts.push(`${data.skipped_out_of_range} out of float range`);
                        if (data.skipped_duplicates) parts.push(`${data.skipped_duplicates} already stored`);
                        if (data.skipped_malformed) parts.push(`${data.skipped_malformed} unreadable`);
                        if (parts.length === 1 && data.skipped) parts.push(`Skipped ${data.skipped}`);

                        setStatus(parts.join(' · '), data.imported > 0 ? 'text-emerald-400' : 'text-zinc-400');
                    }
                } catch {
                    setStatus('Import failed.', 'text-red-400');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Import';
                }
            });
        });
    </script>
@endpush
