@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tracked Skins</h1>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <h2 class="text-sm font-medium">Add a skin</h2>

        <form method="POST" action="{{ route('skins.store') }}"
              class="mt-4 grid gap-4 sm:grid-cols-[1fr_8rem_8rem_9rem_auto] sm:items-end">
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
                <p class="text-xs text-zinc-500">
                    Must match the CSFloat name exactly, including the star
                    (<span class="text-zinc-300">★</span>) and the wear suffix.
                    Example: <span class="text-zinc-300">★ Karambit | Doppler (Factory New)</span>
                    or <span class="text-zinc-300">AK-47 | Redline (Field-Tested)</span>.
                </p>
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
                <th class="px-5 py-3 font-medium">Import</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($skins as $skin)
                <tr class="border-b border-zinc-800/60 last:border-0">
                    <td class="px-5 py-3">
                        <a href="{{ route('skins.sales', $skin) }}" class="hover:text-sky-400">{{ $skin->market_hash_name }}</a>
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
                    <td class="px-5 py-3 whitespace-nowrap">
                        <button type="button"
                                class="js-import text-sm text-zinc-400 hover:text-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                                data-url="{{ route('skins.import', $skin) }}">
                            Import
                        </button>
                        <span class="js-import-status ml-2 text-xs"></span>
                    </td>

                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-4">
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
                    <td colspan="7" class="px-5 py-8 text-center text-zinc-500">No tracked skins yet.</td>
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
                btn.disabled = true;
                btn.textContent = 'Importing…';
                status.className = 'js-import-status ml-2 text-xs text-zinc-500';
                status.textContent = '';

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
                        status.className = 'js-import-status ml-2 text-xs '
                            + (data.rate_limited ? 'text-amber-400' : 'text-red-400');
                        status.textContent = data.message || 'Import failed.';
                        status.title = data.category === 'auth'
                            ? 'The server-side CSFloat API key was rejected.'
                            : '';
                        return;
                    }

                    if (data.no_sales) {
                        status.className = 'js-import-status ml-2 text-xs text-amber-400';
                        status.textContent = 'No sales found for this name';
                        status.title = 'CSFloat has no sales recorded for this exact market hash '
                            + 'name. Check the star (★) and the wear suffix — they must match exactly.';
                    } else {
                        const parts = [`Imported ${data.imported}`];
                        if (data.skipped_wrong_phase) parts.push(`${data.skipped_wrong_phase} wrong phase`);
                        if (data.skipped_out_of_range) parts.push(`${data.skipped_out_of_range} out of float range`);
                        if (data.skipped_duplicates) parts.push(`${data.skipped_duplicates} already stored`);
                        if (data.skipped_malformed) parts.push(`${data.skipped_malformed} unreadable`);
                        if (parts.length === 1 && data.skipped) parts.push(`Skipped ${data.skipped}`);

                        status.className = 'js-import-status ml-2 text-xs '
                            + (data.imported > 0 ? 'text-emerald-400' : 'text-zinc-400');
                        status.textContent = parts.join(' · ');
                        status.title = '';
                    }
                } catch {
                    status.className = 'js-import-status ml-2 text-xs text-red-400';
                    status.textContent = 'Import failed.';
                    status.title = '';
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Import';
                }
            });
        });
    </script>
@endpush
