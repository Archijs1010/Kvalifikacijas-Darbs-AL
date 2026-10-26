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

    @if ($chart->isEmpty())
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 px-5 py-8 text-center text-zinc-500">
            No sales to analyze yet.
        </div>
    @else
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Period</p>
                    <div class="flex gap-1">
                        <button type="button" data-range="7"
                                class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">7 days</button>
                        <button type="button" data-range="30"
                                class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">30 days</button>
                        <button type="button" data-range="90"
                                class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">90 days</button>
                        <button type="button" data-range="365"
                                class="js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800">1 year</button>
                        <button type="button" data-range="all"
                                class="js-range rounded-lg bg-sky-600 px-3 py-1 text-sm font-medium text-white">All</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Datasets</p>

                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" value="price" class="js-dataset h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500" checked>
                        Price
                    </label>

                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" value="float" class="js-dataset h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500">
                        Float
                    </label>

                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" value="volume" class="js-dataset h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500">
                        Sales volume
                    </label>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <div id="chart-wrap" class="h-80">
                <canvas id="price-chart"></canvas>
            </div>
            <p id="chart-empty" class="hidden py-8 text-center text-sm text-zinc-500">No sales in this period.</p>
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
    @if ($chart->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                const points = @json($chart);
                const DAY = 24 * 60 * 60 * 1000;
                const RANGES = { '7': 7, '30': 30, '90': 90, '365': 365, 'all': null };
                const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const t = (name, alpha) => window.CSFloatTheme.color(name, alpha);

                const idleClass = 'js-range rounded-lg px-3 py-1 text-sm text-zinc-400 hover:bg-zinc-800';
                const activeClass = 'js-range rounded-lg bg-sky-600 px-3 py-1 text-sm font-medium text-white';

                const chartWrap = document.getElementById('chart-wrap');
                const chartEmpty = document.getElementById('chart-empty');

                const timestamp = (point) => new Date(point.date.replace(' ', 'T')).getTime();

                const withinRange = (days) => {
                    if (days === null) return points;
                    const cutoff = Date.now() - days * DAY;
                    return points.filter((point) => timestamp(point) >= cutoff);
                };

                const pad = (value) => String(value).padStart(2, '0');

                const useMonthly = (days, data) => {
                    if (days === 365) return true;
                    if (days !== null) return false;
                    if (data.length < 2) return false;
                    const span = (timestamp(data[data.length - 1]) - timestamp(data[0])) / DAY;
                    return span > 120;
                };

                const average = (values) => values.reduce((sum, value) => sum + value, 0) / values.length;

                const aggregate = (data, monthly) => {
                    const groups = new Map();

                    data.forEach((point) => {
                        const date = new Date(point.date.replace(' ', 'T'));
                        const key = monthly
                            ? date.getFullYear() + '-' + pad(date.getMonth() + 1)
                            : date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());

                        if (!groups.has(key)) {
                            groups.set(key, { key: key, date: date, prices: [], floats: [], count: 0 });
                        }

                        const group = groups.get(key);
                        group.prices.push(point.price);
                        if (point.float !== null && point.float !== undefined) group.floats.push(point.float);
                        group.count += 1;
                    });

                    return Array.from(groups.values())
                        .sort((a, b) => a.key.localeCompare(b.key))
                        .map((group) => ({
                            label: monthly
                                ? MONTHS[group.date.getMonth()] + ' ' + group.date.getFullYear()
                                : MONTHS[group.date.getMonth()] + ' ' + group.date.getDate(),
                            price: average(group.prices),
                            float: group.floats.length ? average(group.floats) : null,
                            volume: group.count,
                        }));
                };

                const chart = new Chart(document.getElementById('price-chart'), {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                type: 'line',
                                label: 'Price',
                                data: [],
                                yAxisID: 'y',
                                borderColor: t('--accent-muted', 1),
                                backgroundColor: t('--accent-muted', 0.1),
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2,
                                borderWidth: 2,
                            },
                            {
                                type: 'line',
                                label: 'Float',
                                data: [],
                                yAxisID: 'yFloat',
                                borderColor: t('--success', 1),
                                backgroundColor: t('--success', 0.1),
                                tension: 0.3,
                                pointRadius: 2,
                                borderWidth: 2,
                                spanGaps: true,
                            },
                            {
                                type: 'bar',
                                label: 'Sales volume',
                                data: [],
                                yAxisID: 'yVolume',
                                backgroundColor: t('--warning', 0.35),
                                borderColor: t('--warning', 1),
                                borderWidth: 1,
                                borderRadius: 3,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: {
                                ticks: { color: t('--text-muted', 1), autoSkip: true, maxRotation: 0 },
                                grid: { color: t('--border', 0.6) },
                            },
                            y: {
                                position: 'left',
                                ticks: { color: t('--text-muted', 1), callback: (value) => '$' + value },
                                grid: { color: t('--border', 0.6) },
                            },
                            yFloat: {
                                position: 'right',
                                min: 0,
                                max: 1,
                                display: false,
                                ticks: { color: t('--text-muted', 1) },
                                grid: { drawOnChartArea: false },
                            },
                            yVolume: {
                                position: 'right',
                                display: false,
                                beginAtZero: true,
                                ticks: { color: t('--text-muted', 1), precision: 0 },
                                grid: { drawOnChartArea: false },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (item) => {
                                        if (item.datasetIndex === 0) {
                                            return 'Price: $' + item.parsed.y.toFixed(2);
                                        }
                                        if (item.datasetIndex === 1) {
                                            return 'Float: ' + (item.parsed.y === null ? '—' : item.parsed.y.toFixed(6));
                                        }
                                        return 'Sales: ' + item.parsed.y;
                                    },
                                },
                            },
                        },
                    },
                });

                const datasetIndex = { price: 0, float: 1, volume: 2 };

                const applyToggles = () => {
                    document.querySelectorAll('.js-dataset').forEach((input) => {
                        const index = datasetIndex[input.value];
                        chart.data.datasets[index].hidden = !input.checked;

                        if (input.value === 'float') {
                            chart.options.scales.yFloat.display = input.checked;
                        }

                        if (input.value === 'volume') {
                            chart.options.scales.yVolume.display = input.checked;
                        }
                    });

                    chart.update();
                };

                document.querySelectorAll('.js-dataset').forEach((input) => {
                    input.addEventListener('change', applyToggles);
                });

                const recolor = () => {
                    const price = chart.data.datasets[0];
                    const float = chart.data.datasets[1];
                    const volume = chart.data.datasets[2];

                    price.borderColor = t('--accent-muted', 1);
                    price.backgroundColor = t('--accent-muted', 0.1);
                    float.borderColor = t('--success', 1);
                    float.backgroundColor = t('--success', 0.1);
                    volume.backgroundColor = t('--warning', 0.35);
                    volume.borderColor = t('--warning', 1);

                    chart.options.scales.x.ticks.color = t('--text-muted', 1);
                    chart.options.scales.x.grid.color = t('--border', 0.6);
                    chart.options.scales.y.ticks.color = t('--text-muted', 1);
                    chart.options.scales.y.grid.color = t('--border', 0.6);
                    chart.options.scales.yFloat.ticks.color = t('--text-muted', 1);
                    chart.options.scales.yVolume.ticks.color = t('--text-muted', 1);

                    chart.update();
                };

                document.addEventListener('themechange', recolor);

                const render = (range) => {
                    const days = RANGES[range];
                    const data = withinRange(days);
                    const monthly = useMonthly(days, data);
                    const buckets = aggregate(data, monthly);

                    chart.data.labels = buckets.map((bucket) => bucket.label);
                    chart.data.datasets[0].data = buckets.map((bucket) => bucket.price);
                    chart.data.datasets[1].data = buckets.map((bucket) => bucket.float);
                    chart.data.datasets[2].data = buckets.map((bucket) => bucket.volume);

                    chartWrap.classList.toggle('hidden', buckets.length === 0);
                    chartEmpty.classList.toggle('hidden', buckets.length !== 0);

                    chart.update();
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
                applyToggles();
            })();
        </script>
    @endif
@endpush
