@extends('layouts.app')

@section('content')
    <h1 class="text-xl font-semibold">Dashboard</h1>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Total tracked skins</p>
            <p class="mt-2 text-3xl font-semibold">{{ $totalSkins }}</p>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Total sales</p>
            <p class="mt-2 text-3xl font-semibold">{{ $totalSales }}</p>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5 flex flex-col items-start gap-3">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Import all sales</p>
            <p class="text-sm text-zinc-500">Imports every enabled skin once, one after another.</p>
            <form id="import-form" action="{{ route('import-sales') }}" method="POST">
                @csrf
                <button id="import-btn" type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg id="import-spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span id="import-label">Import All Sales</span>
                </button>
            </form>
        </div>
    </div>

    <div id="import-summary" class="mt-4 hidden rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <div class="flex items-center justify-between gap-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Import summary</p>
            <button id="summary-dismiss" type="button" class="text-sm text-zinc-500 hover:text-zinc-300">Dismiss</button>
        </div>

        <div class="mt-3 grid gap-4 sm:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Successful skins</p>
                <p id="sum-successful" class="mt-1 text-3xl font-semibold text-emerald-400">0</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Failed skins</p>
                <p id="sum-failed" class="mt-1 text-3xl font-semibold text-red-400">0</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">New sales imported</p>
                <p id="sum-imported" class="mt-1 text-3xl font-semibold">0</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Duplicate sales skipped</p>
                <p id="sum-duplicates" class="mt-1 text-3xl font-semibold">0</p>
            </div>
        </div>

        <div id="import-error"
             class="mt-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300"></div>

        <div id="rate-limit-note"
             class="mt-4 hidden rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-300"></div>

        <div id="failed-list" class="mt-4 hidden text-sm">
            <span class="text-zinc-500">Failed:</span>
            <span id="failed-names" class="text-red-400"></span>
        </div>

        <div id="no-sales-list" class="mt-2 hidden text-sm">
            <span class="text-zinc-500">No sales found for:</span>
            <span id="no-sales-names" class="text-zinc-300"></span>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const form = document.getElementById('import-form');
        const btn = document.getElementById('import-btn');
        const spinner = document.getElementById('import-spinner');
        const label = document.getElementById('import-label');
        const summary = document.getElementById('import-summary');

        const show = (id) => document.getElementById(id).classList.remove('hidden');
        const hide = (id) => document.getElementById(id).classList.add('hidden');
        const set = (id, value) => { document.getElementById(id).textContent = value; };
        const names = (value) => value.join(', ');

        document.getElementById('summary-dismiss').addEventListener('click', () => hide('import-summary'));

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            btn.disabled = true;
            spinner.classList.remove('hidden');
            label.textContent = 'Importing…';
            hide('import-summary');
            hide('import-error');
            hide('rate-limit-note');
            hide('failed-list');
            hide('no-sales-list');

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Import failed');

                set('sum-successful', data.successful_skins.length);
                set('sum-failed', data.failed_skins.length);
                set('sum-imported', data.imported);
                set('sum-duplicates', data.duplicates);

                if (data.failed_skins.length) {
                    set('failed-names', names(data.failed_skins));
                    show('failed-list');
                }

                if (data.no_sales_skins.length) {
                    set('no-sales-names', names(data.no_sales_skins));
                    show('no-sales-list');
                }

                if (data.rate_limited) {
                    const note = document.getElementById('rate-limit-note');
                    const notAttempted = data.not_attempted.length
                        ? ' Not attempted: ' + names(data.not_attempted) + '.'
                        : '';
                    note.textContent = 'CSFloat rate limit reached — the run was stopped early so no more '
                        + 'requests were sent.' + notAttempted;
                    show('rate-limit-note');
                }

                show('import-summary');
            } catch (err) {
                set('import-error', err instanceof Error ? err.message : 'Import failed.');
                show('import-error');
                show('import-summary');
            } finally {
                btn.disabled = false;
                spinner.classList.add('hidden');
                label.textContent = 'Import All Sales';
            }
        });
    </script>
@endpush
