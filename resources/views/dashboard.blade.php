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
                        class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    Import Sales
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
            const status = document.getElementById('import-status');
            btn.disabled = true;
            btn.textContent = 'Importing…';
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
                status.textContent = 'Imported: ' + data.imported + ' · Skipped: ' + data.skipped + failed;
            } catch {
                status.textContent = 'Import failed.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Import Sales';
            }
        });
    </script>
@endpush
