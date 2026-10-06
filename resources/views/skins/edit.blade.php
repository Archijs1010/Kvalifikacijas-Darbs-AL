@extends('layouts.app')

@section('content')
    <h1 class="text-xl font-semibold">Edit skin</h1>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <form method="POST" action="{{ route('skins.update', $skin) }}"
              class="grid gap-4 sm:grid-cols-2">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-1 sm:col-span-2">
                <label for="market_hash_name" class="text-xs uppercase tracking-wide text-zinc-500">Market hash name</label>
                <input id="market_hash_name" name="market_hash_name" type="text" required
                       value="{{ old('market_hash_name', $skin->market_hash_name) }}"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('market_hash_name')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="min_float" class="text-xs uppercase tracking-wide text-zinc-500">Min float</label>
                <input id="min_float" name="min_float" type="number" min="0" max="1" step="0.0001"
                       value="{{ old('min_float', $skin->min_float) }}"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('min_float')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="max_float" class="text-xs uppercase tracking-wide text-zinc-500">Max float</label>
                <input id="max_float" name="max_float" type="number" min="0" max="1" step="0.0001"
                       value="{{ old('max_float', $skin->max_float) }}"
                       class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('max_float')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-300 sm:col-span-2">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $skin->enabled))
                       class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-sky-600 focus:ring-sky-500">
                Track this skin
            </label>

            <div class="flex items-center gap-3 sm:col-span-2">
                <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                    Save changes
                </button>
                <a href="{{ route('skins.index') }}"
                   class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
