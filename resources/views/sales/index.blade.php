@extends('layouts.app')

@section('content')
    <h1 class="text-xl font-semibold">Sales History</h1>

    <form method="GET" action="{{ route('sales.index') }}"
          class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
        <div class="flex flex-col gap-1">
            <label for="skin" class="text-xs uppercase tracking-wide text-zinc-500">Skin</label>
            <select id="skin" name="skin"
                    class="w-64 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                <option value="">All skins</option>
                @foreach ($skins as $name)
                    <option value="{{ $name }}" @selected(($filters['skin'] ?? '') === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="min_float" class="text-xs uppercase tracking-wide text-zinc-500">Min float</label>
            <input id="min_float" name="min_float" type="number" min="0" max="1" step="0.0001"
                   value="{{ $filters['min_float'] ?? '' }}"
                   class="w-32 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1">
            <label for="max_float" class="text-xs uppercase tracking-wide text-zinc-500">Max float</label>
            <input id="max_float" name="max_float" type="number" min="0" max="1" step="0.0001"
                   value="{{ $filters['max_float'] ?? '' }}"
                   class="w-32 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1">
            <label for="phase" class="text-xs uppercase tracking-wide text-zinc-500">Phase</label>
            <select id="phase" name="phase"
                    class="w-40 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                <option value="">All phases</option>
                @foreach ($phases as $phase)
                    <option value="{{ $phase }}" @selected(($filters['phase'] ?? '') === $phase)>{{ $phase }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
            Apply
        </button>
        <a href="{{ route('sales.index') }}"
           class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">
            Clear
        </a>
    </form>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-zinc-800 text-left text-xs uppercase tracking-wide text-zinc-500">
                <th class="px-5 py-3 font-medium">Date</th>
                <th class="px-5 py-3 font-medium">Skin</th>
                <th class="px-5 py-3 font-medium">Phase</th>
                <th class="px-5 py-3 font-medium text-right">Price</th>
                <th class="px-5 py-3 font-medium text-right">Float</th>
                <th class="px-5 py-3 font-medium text-right">Seed</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($sales as $sale)
                <tr class="border-b border-zinc-800/60 last:border-0">
                    <td class="whitespace-nowrap px-5 py-3 text-zinc-400">{{ $sale->sold_at?->format('M j, Y H:i') ?? '—' }}</td>
                    <td class="px-5 py-3">{{ $sale->market_hash_name }}</td>
                    <td class="px-5 py-3 text-zinc-400">{{ $sale->phase ?? '—' }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right">${{ number_format((float) $sale->price, 2) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-400">{{ $sale->float_value === null ? '—' : number_format((float) $sale->float_value, 6) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-400">{{ $sale->paint_seed ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-zinc-500">No sales found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $sales->links() }}
@endsection
