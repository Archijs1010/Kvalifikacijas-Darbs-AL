@extends('layouts.app')

@section('content')
    <h1 class="text-xl font-semibold">Note</h1>
    <p class="mt-1 text-sm text-zinc-400">{{ $skin->market_hash_name }}</p>

    <div class="mt-4 max-w-2xl rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <form method="POST" action="{{ route('skins.note.update', $skin) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-1">
                <label for="note" class="text-xs uppercase tracking-wide text-zinc-500">Private note</label>
                <textarea id="note" name="note" rows="5" maxlength="1000"
                          placeholder="Anything you want to remember about this skin…"
                          class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">{{ old('note', $skin->note) }}</textarea>
                @error('note')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                    Save note
                </button>
                <a href="{{ route('skins.index') }}"
                   class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">
                    Cancel
                </a>
            </div>
        </form>

        @if ($skin->note)
            <form method="POST" action="{{ route('skins.note.destroy', $skin) }}"
                  class="mt-4 border-t border-zinc-800 pt-4"
                  onsubmit="return confirm('Delete this note?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="text-sm text-zinc-400 hover:text-red-400">Delete note</button>
            </form>
        @endif
    </div>
@endsection
