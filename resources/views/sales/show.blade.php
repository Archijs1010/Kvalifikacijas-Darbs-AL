@extends('layouts.app')

@section('content')
    <a href="{{ route('sales.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">&larr; Sales History</a>

    <h1 class="mt-1 text-xl font-semibold">
        <a href="{{ route('sales.index', ['skin' => $sale->market_hash_name]) }}" class="hover:text-sky-400">
            {{ $sale->market_hash_name }}
        </a>
    </h1>

    <div class="grid gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5 sm:grid-cols-3 lg:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Sold at</p>
            <p class="mt-1 text-lg font-semibold">{{ $sale->sold_at?->format('M j, Y H:i') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Price</p>
            <p class="mt-1 text-lg font-semibold">${{ number_format((float) $sale->price, 2) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Float</p>
            <p class="mt-1 text-lg font-semibold">{{ $sale->float_value === null ? '—' : number_format((float) $sale->float_value, 6) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Phase</p>
            <p class="mt-1 text-lg font-semibold">{{ $sale->phase ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Paint seed</p>
            <p class="mt-1 text-lg font-semibold">{{ $sale->paint_seed ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-zinc-500">Paint index</p>
            <p class="mt-1 text-lg font-semibold">{{ $sale->paint_index ?? '—' }}</p>
        </div>
        <div class="sm:col-span-2">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Sale ID</p>
            <p class="mt-1 break-all font-mono text-sm text-zinc-300">{{ $sale->sale_id }}</p>
        </div>
    </div>

    <details class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <summary class="cursor-pointer text-xs uppercase tracking-wide text-zinc-500">Raw API Data</summary>

        <pre class="mt-3 max-h-[32rem] overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-300">{{ $rawJson }}</pre>
    </details>
@endsection
