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

    @if ($chart->isNotEmpty())
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <div class="mb-4 flex items-center justify-between gap-4">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Price history</p>
                <div class="flex gap-1">
                    <button type="button" data-range="7"
                            class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">7 days</button>
                    <button type="button" data-range="30"
                            class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">30 days</button>
                    <button type="button" data-range="90"
                            class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">90 days</button>
                    <button type="button" data-range="all"
                            class="js-range rounded-lg bg-sky-600 px-3 py-1 text-sm font-medium text-white">All</button>
                </div>
            </div>

            <div id="chart-wrap" class="h-80">
                <canvas id="price-chart"></canvas>
            </div>
            <p id="chart-empty" class="hidden py-8 text-center text-sm text-zinc-500">No sales in this range.</p>
        </div>
    @endif

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

@push('scripts')
    @if ($chart->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            const points = @json($chart);
            const rangeDays = { '7': 7, '30': 30, '90': 90, 'all': null };

            const idleClass = 'js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800';
            const activeClass = 'js-range rounded-lg bg-sky-600 px-3 py-1 text-sm font-medium text-white';

            const chartWrap = document.getElementById('chart-wrap');
            const chartEmpty = document.getElementById('chart-empty');

            const DAY = 24 * 60 * 60 * 1000;
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            let currentDates = [];

            const timestamp = (point) => new Date(point.date.replace(' ', 'T')).getTime();

            const daysAgo = (point) => Math.max(0, Math.round((Date.now() - timestamp(point)) / DAY));

            // A 7 day window reads best day by day, a month week by week, a
            // quarter month by month, and anything longer in quarters.
            const tickStep = (maxDaysAgo) => {
                if (maxDaysAgo <= 7) return 1;
                if (maxDaysAgo <= 14) return 2;
                if (maxDaysAgo <= 31) return 7;
                if (maxDaysAgo <= 90) return 30;
                return Math.ceil(maxDaysAgo / 4);
            };

            // The axis speaks in "days ago" while the tooltip keeps the exact
            // sale timestamp, so the raw dates stay available next to the data.
            const axisLabels = (data) => {
                if (data.length === 0) return [];

                const ago = data.map(daysAgo);
                const step = tickStep(ago[0]);
                const labelled = new Set();

                return data.map((point, index) => {
                    const days = ago[index];

                    if (labelled.has(days)) return '';
                    labelled.add(days);

                    if (index === 0 || days % step === 0) return days + 'd ago';

                    return '';
                });
            };

            const formatDate = (value) => {
                const date = new Date(value.replace(' ', 'T'));
                const pad = (number) => String(number).padStart(2, '0');

                return MONTHS[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear()
                    + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
            };

            const withinRange = (days) => {
                if (days === null) return points;
                const cutoff = Date.now() - days * DAY;
                return points.filter((point) => timestamp(point) >= cutoff);
            };

            const chart = new Chart(document.getElementById('price-chart'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Price ($)',
                        data: [],
                        borderColor: '#38bdf8',
                        backgroundColor: 'rgba(56, 189, 248, 0.1)',
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
                            ticks: { color: '#a1a1aa', autoSkip: false, maxRotation: 0 },
                            grid: { color: 'rgba(255, 255, 255, 0.06)' },
                        },
                        y: {
                            ticks: { color: '#a1a1aa', callback: (value) => '$' + value },
                            grid: { color: 'rgba(255, 255, 255, 0.06)' },
                        },
                    },
                    plugins: {
                        legend: { labels: { color: '#e4e4e7' } },
                        tooltip: {
                            callbacks: {
                                title: (items) => formatDate(currentDates[items[0].dataIndex] ?? ''),
                                label: (item) => '$' + item.parsed.y.toFixed(2),
                            },
                        },
                    },
                },
            });

            const render = (range) => {
                const data = withinRange(rangeDays[range]);
                currentDates = data.map((point) => point.date);

                chart.data.labels = axisLabels(data);
                chart.data.datasets[0].data = data.map((point) => point.price);
                chart.update();

                chartWrap.classList.toggle('hidden', data.length === 0);
                chartEmpty.classList.toggle('hidden', data.length !== 0);
            };

            document.querySelectorAll('.js-range').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.js-range').forEach((other) => {
                        other.className = other === button ? activeClass : idleClass;
                    });

                    render(button.dataset.range);
                });
            });

            render('all');
        </script>
    @endif
@endpush
