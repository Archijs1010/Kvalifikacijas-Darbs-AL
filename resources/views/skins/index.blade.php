@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tracked Skins</h1>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-zinc-800 text-left text-xs uppercase tracking-wide text-zinc-500">
                <th class="px-5 py-3 font-medium">Skin</th>
                <th class="px-5 py-3 font-medium">Min Float</th>
                <th class="px-5 py-3 font-medium">Max Float</th>
                <th class="px-5 py-3 font-medium">Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($skins as $skin)
                <tr class="border-b border-zinc-800/60 last:border-0">
                    <td class="px-5 py-3">{{ $skin->market_hash_name }}</td>
                    <td class="px-5 py-3 text-zinc-400">{{ $skin->min_float ?? '—' }}</td>
                    <td class="px-5 py-3 text-zinc-400">{{ $skin->max_float ?? '—' }}</td>
                    <td class="px-5 py-3">
                        @if ($skin->enabled)
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs text-emerald-400">Enabled</span>
                        @else
                            <span class="rounded-full bg-zinc-700/40 px-2.5 py-0.5 text-xs text-zinc-400">Disabled</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-5 py-8 text-center text-zinc-500">No tracked skins yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
