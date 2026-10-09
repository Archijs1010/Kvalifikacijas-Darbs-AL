@extends('layouts.app')

@section('content')
    @php
        $money = fn ($value) => $value === null ? '—' : '$'.number_format((float) $value, 2);

        $floatRange = match (true) {
            $skin->min_float === null && $skin->max_float === null => 'Any',
            $skin->max_float === null => '≥ '.$skin->min_float,
            $skin->min_float === null => '≤ '.$skin->max_float,
            default => $skin->min_float.' – '.$skin->max_float,
        };
    @endphp

    <a href="{{ route('skins.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">&larr; Tracked skins</a>

    <h1 class="mt-1 text-xl font-semibold">{{ $skin->market_hash_name }}</h1>

    <div class="grid gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5 sm:grid-cols-3 lg:grid-cols-6">
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Float range</p>
            <p class="mt-1 text-lg font-semibold">{{ $floatRange }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Stored sales</p>
            <p class="mt-1 text-lg font-semibold">{{ $stats['count'] }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Lowest price</p>
            <p class="mt-1 text-lg font-semibold">{{ $money($stats['lowest']) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Highest price</p>
            <p class="mt-1 text-lg font-semibold">{{ $money($stats['highest']) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Average price</p>
            <p class="mt-1 text-lg font-semibold">{{ $money($stats['average']) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Median price</p>
            <p class="mt-1 text-lg font-semibold">{{ $money($stats['median']) }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-zinc-800 text-left text-xs uppercase tracking-wide text-zinc-500">
                <th class="px-5 py-3 font-medium">Date</th>
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
                    <td class="px-5 py-3 text-zinc-400">{{ $sale->phase ?? '—' }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right">${{ number_format((float) $sale->price, 2) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-400">{{ $sale->float_value === null ? '—' : number_format((float) $sale->float_value, 6) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-400">{{ $sale->paint_seed ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-zinc-500">No sales stored for this skin yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $sales->links() }}
@endsection
