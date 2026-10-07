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
            <p class="text-xs uppercase tracking-wide text-zinc-500">Manual import</p>
            <form id="import-form" action="{{ route('import-sales') }}" method="POST">
                @csrf
                <button id="import-btn" type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg id="import-spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span id="import-label">Import Sales</span>
                </button>
            </form>
            <p id="import-status" class="text-sm text-zinc-400"></p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const form = document.getElementById('import-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('import-btn');
            const spinner = document.getElementById('import-spinner');
            const label = document.getElementById('import-label');
            const status = document.getElementById('import-status');
            btn.disabled = true;
            spinner.classList.remove('hidden');
            label.textContent = 'Importing…';
            status.textContent = '';
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
                const failed = data.failed_skins.length ? ' — failed: ' + data.failed_skins.join(', ') : '';
                const unknown = data.no_sales_skins.length
                    ? ' — no sales found: ' + data.no_sales_skins.join(', ')
                    : '';
                status.textContent = 'Imported: ' + data.imported + ' · Skipped: ' + data.skipped + failed + unknown;
            } catch {
                status.textContent = 'Import failed.';
            } finally {
                btn.disabled = false;
                spinner.classList.add('hidden');
                label.textContent = 'Import Sales';
            }
        });
    </script>
@endpush
