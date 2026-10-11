@extends('layouts.app')

@section('content')
    <h1 class="text-xl font-semibold">Analytics</h1>

    <form method="GET" action="{{ route('analytics.index') }}"
          class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
        <div class="flex flex-col gap-1">
            <label for="skin" class="text-xs uppercase tracking-wide text-zinc-500">Skin</label>
            <select id="skin" name="skin"
                    class="w-64 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @foreach ($skins as $name)
                    <option value="{{ $name }}" @selected($selected === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
            Apply
        </button>
    </form>

    @if (count($chart['prices']) === 0)
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 px-5 py-8 text-center text-zinc-500">
            No sales to analyze yet.
        </div>
    @else
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <div class="h-80">
                <canvas id="price-chart"></canvas>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Average price</p>
                <p class="mt-2 text-2xl font-semibold">${{ number_format($stats['average'], 2) }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Highest</p>
                <p class="mt-2 text-2xl font-semibold">${{ number_format($stats['highest'], 2) }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Lowest</p>
                <p class="mt-2 text-2xl font-semibold">${{ number_format($stats['lowest'], 2) }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Total sales</p>
                <p class="mt-2 text-2xl font-semibold">{{ $stats['total'] }}</p>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @if (count($chart['prices']) > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                const color = (name, alpha) => window.CSFloatTheme.color(name, alpha);

                const chart = new Chart(document.getElementById('price-chart'), {
                    type: 'line',
                    data: {
                        labels: @json($chart['labels']),
                        datasets: [{
                            label: 'Price ($)',
                            data: @json($chart['prices']),
                            borderColor: color('--accent-muted', 1),
                            backgroundColor: color('--accent-muted', 0.1),
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ticks: { color: color('--text-muted', 1) },
                                grid: { color: color('--border', 0.6) },
                            },
                            y: {
                                ticks: { color: color('--text-muted', 1), callback: (v) => '$' + v },
                                grid: { color: color('--border', 0.6) },
                            },
                        },
                        plugins: { legend: { labels: { color: color('--text', 1) } } },
                    },
                });

                const recolor = () => {
                    const dataset = chart.data.datasets[0];
                    dataset.borderColor = color('--accent-muted', 1);
                    dataset.backgroundColor = color('--accent-muted', 0.1);

                    chart.options.scales.x.ticks.color = color('--text-muted', 1);
                    chart.options.scales.x.grid.color = color('--border', 0.6);
                    chart.options.scales.y.ticks.color = color('--text-muted', 1);
                    chart.options.scales.y.grid.color = color('--border', 0.6);
                    chart.options.plugins.legend.labels.color = color('--text', 1);

                    chart.update();
                };

                document.addEventListener('themechange', recolor);
            })();
        </script>
    @endif
@endpush
